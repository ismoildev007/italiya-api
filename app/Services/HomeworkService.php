<?php

namespace App\Services;

use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TestAssignment;
use App\Models\TestTemplate;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Uy ishi: o'qituvchi guruhga/o'quvchiga beradi (matn, fayl, test yoki darslik),
 * o'quvchi topshiradi (matn/fayl/audio), o'qituvchi 0..100 baholaydi.
 * Uy ishi o'rtachasi sertifikat balining 30% ini tashkil qiladi.
 */
class HomeworkService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly TestService $tests,
    ) {}

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return Homework::query()
            ->with(['teacher.user', 'schoolClass', 'student', 'testTemplate', 'unit'])
            ->withCount(['submissions', 'submissions as pending_count' => fn ($q) => $q->where('status', HomeworkSubmission::STATUS_PENDING)])
            ->search($filters['search'] ?? null, ['title'])
            ->filter($filters, [
                'class_id' => 'class_id',
                'student_id' => 'student_id',
                'teacher_id' => 'teacher_id',
                'level' => 'level',
                'status' => 'status',
            ])
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function save(array $data, ?Homework $homework = null, ?UploadedFile $attachment = null): Homework
    {
        return DB::transaction(function () use ($data, $homework, $attachment) {
            $payload = [
                'school_id' => config('school.id'),
                'teacher_id' => $data['teacher_id'] ?? $homework?->teacher_id ?? Teacher::where('user_id', auth()->id())->value('id') ?? Teacher::orderBy('id')->value('id'),
                'class_id' => $data['class_id'] ?? $homework?->class_id,
                'student_id' => $data['student_id'] ?? $homework?->student_id,
                'level' => $data['level'] ?? $homework?->level,
                'title' => $data['title'] ?? $homework?->title,
                'description' => $data['description'] ?? $homework?->description,
                'test_template_id' => $data['test_template_id'] ?? $homework?->test_template_id,
                'course_unit_id' => $data['course_unit_id'] ?? $homework?->course_unit_id,
                'due_at' => $data['due_at'] ?? $homework?->due_at,
                'max_score' => $data['max_score'] ?? $homework?->max_score ?? 100,
                'status' => $data['status'] ?? $homework?->status ?? 'active',
            ];

            $isNew = $homework === null;
            $homework = $homework ? tap($homework)->update($payload) : Homework::create($payload);

            if ($attachment) {
                $homework->update(['attachment_path' => $attachment->store("homeworks/{$homework->id}", 'public')]);
            }

            // Test biriktirilgan bo'lsa — test ham avtomatik biriktiriladi (kind = homework)
            if ($isNew && $homework->test_template_id) {
                $template = TestTemplate::find($homework->test_template_id);

                if ($template && ! TestAssignment::where('test_template_id', $template->id)
                    ->where('class_id', $homework->class_id)->where('student_id', $homework->student_id)->exists()) {
                    $this->tests->assign($template, [
                        'class_id' => $homework->class_id,
                        'student_id' => $homework->student_id,
                        'teacher_id' => $homework->teacher_id,
                        'due_at' => $homework->due_at,
                    ]);
                }
            }

            if ($isNew) {
                $this->notifications->homeworkAssigned($homework->fresh());
            }

            return $homework->fresh(['teacher.user', 'schoolClass', 'student', 'testTemplate', 'unit']);
        });
    }

    public function delete(Homework $homework): void
    {
        $homework->delete();
    }

    public function submissions(Homework $homework): Collection
    {
        return $homework->submissions()->with('student')->orderByDesc('submitted_at')->get();
    }

    /* ---------------- O'quvchi ---------------- */

    /** O'quvchiga tegishli uy ishlari (guruhi yoki shaxsan) — topshirish holati bilan. */
    public function forStudent(Student $student, ?string $status = null): Collection
    {
        $homeworks = Homework::query()
            ->where(fn ($q) => $q->where('student_id', $student->id)->orWhere('class_id', $student->class_id))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->with(['teacher.user', 'testTemplate', 'unit'])
            ->orderByDesc('due_at')->orderByDesc('id')
            ->get();

        $mine = HomeworkSubmission::where('student_id', $student->id)->whereIn('homework_id', $homeworks->pluck('id'))->get()->keyBy('homework_id');

        return $homeworks->each(fn (Homework $hw) => $hw->setRelation('mySubmission', $mine->get($hw->id)));
    }

    public function submit(Homework $homework, Student $student, array $data, ?UploadedFile $file = null, ?UploadedFile $audio = null): HomeworkSubmission
    {
        abort_if($homework->status !== 'active', 422, 'Uy ishi yopilgan.');
        abort_unless($homework->student_id === $student->id || $homework->class_id === $student->class_id, 403, 'Bu uy ishi sizga berilmagan.');

        $submission = HomeworkSubmission::firstOrNew(['homework_id' => $homework->id, 'student_id' => $student->id]);

        abort_if($submission->exists && $submission->status === HomeworkSubmission::STATUS_CHECKED, 422, 'Uy ishi allaqachon tekshirilgan.');

        $submission->fill([
            'school_id' => config('school.id'),
            'text' => $data['text'] ?? $submission->text,
            'test_attempt_id' => $data['test_attempt_id'] ?? $submission->test_attempt_id,
            'submitted_at' => now(),
            'status' => HomeworkSubmission::STATUS_PENDING,
        ]);

        if ($file) {
            $submission->file_path = $file->store("homeworks/{$homework->id}/submissions", 'public');
        }

        if ($audio) {
            $submission->audio_path = $audio->store("homeworks/{$homework->id}/audio", 'public');
        }

        $submission->save();

        return $submission->fresh(['homework', 'student']);
    }

    /* ---------------- O'qituvchi ---------------- */

    public function grade(HomeworkSubmission $submission, float $score, ?string $feedback = null, bool $returned = false): HomeworkSubmission
    {
        $max = $submission->homework->max_score;
        abort_if($score > $max, 422, "Ball {$max} dan oshmasligi kerak.");

        $submission->update([
            'score' => $score,
            'feedback' => $feedback,
            'status' => $returned ? HomeworkSubmission::STATUS_RETURNED : HomeworkSubmission::STATUS_CHECKED,
            'graded_by' => auth()->id(),
            'graded_at' => now(),
        ]);

        $this->notifications->homeworkGraded($submission->fresh(['homework', 'student']));

        return $submission->fresh(['homework', 'student']);
    }

    /** O'quvchining davr bo'yicha uy ishi o'rtachasi (0..100) — sertifikat uchun. */
    public function averageFor(Student $student, ?CarbonInterface $from = null, ?CarbonInterface $to = null): ?float
    {
        $rows = HomeworkSubmission::where('student_id', $student->id)
            ->where('status', HomeworkSubmission::STATUS_CHECKED)
            ->whereNotNull('score')
            ->when($from, fn ($q) => $q->where('graded_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('graded_at', '<=', $to))
            ->with('homework:id,max_score')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        return round($rows->avg(fn ($s) => $s->homework?->max_score ? $s->score / $s->homework->max_score * 100 : $s->score), 2);
    }
}
