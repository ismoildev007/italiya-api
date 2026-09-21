<?php

namespace Database\Seeders;

use App\Models\ClassSubjectTeacher;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TestAnswer;
use App\Models\TestAssignment;
use App\Models\TestAttempt;
use App\Models\TestQuestion;
use App\Models\TestQuestionOption;
use App\Models\TestSection;
use App\Models\TestTemplate;
use App\Services\SkillProgressService;
use App\Support\TextSimilarity;
use Database\Seeders\Data\TestBank;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * IELTS uslubidagi testlar (mock / mashq / uy ishi) har bir daraja uchun,
 * guruhlarga biriktirish, topshirilgan urinishlar va har bir javobning to'liq tarixi.
 */
class TestSeeder extends Seeder
{
    public function run(SkillProgressService $skills): void
    {
        mt_srand(1212);

        $subjectId = Subject::where('code', 'ITA')->value('id') ?? Subject::value('id');
        $teacherByLevel = SchoolClass::with('classTeacher')->get()->groupBy('level')->map(fn ($g) => $g->first()->class_teacher_id);
        $fallbackTeacher = ClassSubjectTeacher::value('teacher_id');
        $templates = [];

        DB::transaction(function () use ($subjectId, $teacherByLevel, $fallbackTeacher, &$templates) {
            foreach (TestBank::templates() as $index => $data) {
                $kindDefaults = config("academy.defaults.kinds.{$data['kind']}");

                $template = TestTemplate::create([
                    'school_id' => config('school.id'),
                    'subject_id' => $subjectId,
                    'teacher_id' => $teacherByLevel->get($data['level']) ?? $fallbackTeacher,
                    'level' => $data['level'],
                    'track' => 'general',
                    'kind' => $data['kind'],
                    'title' => $data['title'],
                    'topic' => $data['topic'],
                    'description' => "{$data['level']} darajasi, «{$data['topic']}» mavzusi bo'yicha ".config("academy.levels.{$data['level']}.title").' testi.',
                    'difficulty' => $data['difficulty'],
                    'generated_by' => $data['generated_by'],
                    'ai_model' => $data['generated_by'] === 'ai' ? config('services.gemini.model') : null,
                    'ai_prompt' => $data['generated_by'] === 'ai' ? "{$data['level']} daraja, «{$data['topic']}» mavzusi bo'yicha 4 bo'limli test generatsiya qil." : null,
                    'ai_generated_at' => $data['generated_by'] === 'ai' ? now()->subDays(20 - $index) : null,
                    'shuffle_questions' => $data['kind'] !== 'mock',
                    'shuffle_options' => true,
                    'duration_minutes' => $data['duration_minutes'],
                    'max_attempts' => $kindDefaults['max_attempts'],
                    'max_plays' => $kindDefaults['max_plays'],
                    'auto_grade_ai' => true,
                    'show_answers' => $data['kind'] === 'practice',
                    'pass_percent' => config('academy.pass_percent'),
                    'is_published' => true,
                ]);

                foreach ($data['sections'] as $sOrder => $sectionData) {
                    $section = TestSection::create([
                        'school_id' => config('school.id'),
                        'test_template_id' => $template->id,
                        'order' => $sOrder + 1,
                        'skill' => $sectionData['skill'],
                        'title' => $sectionData['title'],
                        'instructions' => $sectionData['instructions'] ?? null,
                        'passage' => $sectionData['passage'] ?? null,
                        'media_script' => $sectionData['media_script'] ?? null,
                        'time_limit_minutes' => $sectionData['time_limit_minutes'] ?? null,
                        'ai_generated' => $data['generated_by'] === 'ai',
                    ]);

                    foreach ($sectionData['questions'] as $qOrder => $q) {
                        $question = TestQuestion::create([
                            'school_id' => config('school.id'),
                            'test_template_id' => $template->id,
                            'test_section_id' => $section->id,
                            'order' => $qOrder + 1,
                            'body' => $q['body'],
                            'type' => $q['type'],
                            'skill' => $q['skill'],
                            'answer_mode' => $q['answer_mode'],
                            'score' => $q['score'],
                            'correct_answer' => $q['correct_answer'] ?? null,
                            'model_answer' => $q['model_answer'] ?? null,
                            'meta' => $q['meta'] ?? null,
                            'ai_generated' => $data['generated_by'] === 'ai',
                        ]);

                        foreach ($q['options'] ?? [] as $oOrder => $option) {
                            TestQuestionOption::create([
                                'test_question_id' => $question->id,
                                'order' => $oOrder + 1,
                                'body' => $option['body'],
                                'is_correct' => $option['is_correct'],
                            ]);
                        }
                    }
                }

                $template->recalculateScore();
                $templates[] = $template->fresh();
            }
        });

        $this->command?->info('✅ '.count($templates).' ta test shabloni yaratildi (mock / mashq / uy ishi, bo\'limlar bilan).');

        $this->seedAssignmentsAndAttempts(collect($templates), $skills);
    }

    private function seedAssignmentsAndAttempts($templates, SkillProgressService $skills): void
    {
        $assignmentCount = $attemptCount = $answerCount = 0;

        DB::transaction(function () use ($templates, $skills, &$assignmentCount, &$attemptCount, &$answerCount) {
            foreach ($templates as $index => $template) {
                $classes = SchoolClass::where('level', $template->level)->get();

                foreach ($classes as $class) {
                    $assignment = TestAssignment::create([
                        'school_id' => config('school.id'),
                        'test_template_id' => $template->id,
                        'class_id' => $class->id,
                        'teacher_id' => $template->teacher_id,
                        'target' => TestAssignment::TARGET_CLASS,
                        'starts_at' => now()->subDays(10 - ($index % 7)),
                        'due_at' => now()->addDays(3 + ($index % 5)),
                        'status' => 'active',
                    ]);
                    $assignmentCount++;

                    $questions = $template->questions()->with('options')->get();

                    foreach (Student::where('class_id', $class->id)->get() as $studentIndex => $student) {
                        // Har to'rtinchi o'quvchi hali topshirmagan
                        if ($studentIndex % 4 === 3) {
                            continue;
                        }

                        $attempt = TestAttempt::create([
                            'school_id' => config('school.id'),
                            'test_assignment_id' => $assignment->id,
                            'student_id' => $student->id,
                            'attempt_no' => 1,
                            'started_at' => now()->subDays(4)->addMinutes($studentIndex * 9),
                            'submitted_at' => now()->subDays(4)->addMinutes($studentIndex * 9 + $template->duration_minutes),
                            'status' => TestAttempt::STATUS_GRADED,
                            'question_order' => $questions->pluck('id')->all(),
                            'max_score' => (float) $template->total_score,
                            'source' => $studentIndex % 5 === 0 ? 'telegram' : 'web',
                            'graded_at' => now()->subDays(3),
                        ]);
                        $attemptCount++;

                        $earned = 0.0;
                        $t = 0;

                        foreach ($questions as $qi => $question) {
                            $correct = (($studentIndex * 3 + $qi * 5 + $index) % 10) < 7;
                            $max = (float) $question->score;
                            $row = ['school_id' => config('school.id'), 'test_attempt_id' => $attempt->id, 'test_question_id' => $question->id, 'is_correct' => $correct, 'score' => $correct ? $max : 0.0];

                            if ($question->isChoice()) {
                                $row['selected_option_ids'] = $correct
                                    ? $question->options->where('is_correct', true)->pluck('id')->all()
                                    : array_slice($question->options->where('is_correct', false)->pluck('id')->all(), 0, 1);
                            } elseif ($question->isTrueFalse()) {
                                $row['answer_text'] = $correct ? $question->correct_answer : ($question->correct_answer === 'true' ? 'false' : 'true');
                            } elseif ($question->isSimilarity()) {
                                $row['answer_text'] = $correct ? $question->correct_answer : 'risposta sbagliata';
                                $row['similarity'] = round(TextSimilarity::ratio($row['answer_text'], $question->correct_answer) * 100, 2);
                                $row['plays'] = $question->type === 'dictation' ? 1 : 0;
                            } elseif ($question->type === TestQuestion::TYPE_SPEAKING) {
                                $row['audio_path'] = sprintf('test-answers/audio/attempt-%d-q%d.webm', $attempt->id, $question->id);
                                $row['transcript'] = TestBank::TRANSCRIPTS[$t++ % count(TestBank::TRANSCRIPTS)];
                                $row['transcription_provider'] = 'openai';
                                $row['transcribed_at'] = $attempt->submitted_at;
                                $pct = $correct ? 70 + ($studentIndex * 7) % 25 : 35 + ($studentIndex * 5) % 20;
                                $row['score'] = round($max * $pct / 100, 2);
                                $row['ai_score'] = $row['score'];
                                $row['ai_provider'] = 'gemini';
                                $row['ai_evaluated_at'] = $attempt->submitted_at;
                                $row['ai_feedback'] = ['score_percent' => $pct, 'criteria' => ['fluency' => $pct, 'pronunciation' => $pct - 5, 'grammar' => $pct + 3, 'vocabulary' => $pct], 'feedback' => $pct >= 60 ? 'Yaxshi ravonlik, talaffuzda kichik xatolar bor.' : 'Gaplar qisqa, grammatik xatolar ko\'p — namunaviy javobni takrorlang.'];
                            } else {
                                $row['answer_text'] = $question->model_answer;
                                $pct = $correct ? 65 + ($studentIndex * 9) % 30 : 30 + ($studentIndex * 5) % 25;
                                $row['score'] = round($max * $pct / 100, 2);
                                $row['ai_score'] = $row['score'];
                                $row['ai_provider'] = 'gemini';
                                $row['ai_evaluated_at'] = $attempt->submitted_at;
                                $row['ai_feedback'] = ['score_percent' => $pct, 'criteria' => ['task_response' => $pct, 'grammar' => $pct - 4, 'vocabulary' => $pct + 2, 'coherence' => $pct], 'errors' => [], 'feedback' => $pct >= 60 ? 'Topshiriq to\'liq bajarilgan, bog\'lovchi so\'zlarni ko\'proq ishlating.' : 'Matn qisqa, artikl va fe\'l zamonlarida xatolar bor.'];
                            }

                            $earned += (float) $row['score'];
                            TestAnswer::create($row);
                            $answerCount++;
                        }

                        $percent = round($earned / max(1, (float) $attempt->max_score) * 100, 2);
                        $attempt->update(['total_score' => $earned, 'percent' => $percent]);
                        $skills->applyAttempt($attempt->fresh(['answers.question', 'assignment.template', 'student']));
                    }
                }
            }
        });

        $this->command?->info("✅ {$assignmentCount} ta biriktirma, {$attemptCount} ta urinish, {$answerCount} ta javob (ko'nikma progressi hisoblandi).");
    }
}
