<?php

namespace App\Services;

use App\Models\CourseMaterial;
use App\Models\CourseUnit;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\UnitProgress;
use App\Services\Ai\AiManager;
use App\Services\Ai\LocalAi;
use App\Services\Ai\SpeechService;
use App\Support\Level;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Darsliklar: daraja × yo'nalish × ko'nikma. AI generatsiya, TTS audio,
 * materiallar (video/kitob/fayl) va o'quvchi progressi (necha marta tinglandi).
 * Kunlik dars: PDF majburiy, `available_on` sanasidan oldin o'quvchiga yashirin.
 */
class CourseUnitService
{
    public function __construct(
        private readonly AiManager $ai,
        private readonly SpeechService $speech,
        private readonly MediaPlayService $plays,
    ) {}

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return CourseUnit::query()
            ->with(['teacher.user', 'practiceTest'])
            ->withCount('materials')
            ->search($filters['search'] ?? null, ['title', 'description'])
            ->filter($filters, [
                'level' => 'level',
                'track' => 'track',
                'skill' => 'skill',
                'teacher_id' => 'teacher_id',
                'is_published' => ['is_published', 'bool'],
            ])
            ->orderBy('level')->orderBy('track')->orderBy('order')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function save(array $data, ?CourseUnit $unit = null): CourseUnit
    {
        $payload = [
            'school_id' => config('school.id'),
            'subject_id' => $data['subject_id'] ?? $unit?->subject_id,
            'teacher_id' => $data['teacher_id'] ?? $unit?->teacher_id ?? Teacher::where('user_id', auth()->id())->value('id'),
            'level' => $data['level'] ?? $unit?->level,
            'track' => $data['track'] ?? $unit?->track ?? 'general',
            'order' => $data['order'] ?? $unit?->order ?? $this->nextOrder($data['level'] ?? $unit?->level, $data['track'] ?? $unit?->track ?? 'general'),
            'skill' => $data['skill'] ?? $unit?->skill,
            'title' => $data['title'] ?? $unit?->title,
            'description' => $data['description'] ?? $unit?->description,
            'content' => $data['content'] ?? $unit?->content,
            'media_script' => $data['media_script'] ?? $unit?->media_script,
            'media_url' => $data['media_url'] ?? $unit?->media_url,
            'available_on' => $data['available_on'] ?? $unit?->available_on,
            'max_plays' => array_key_exists('max_plays', $data) ? $data['max_plays'] : $unit?->max_plays,
            'time_limit_minutes' => $data['time_limit_minutes'] ?? $unit?->time_limit_minutes,
            'test_template_id' => $data['test_template_id'] ?? $unit?->test_template_id,
            'is_published' => $data['is_published'] ?? $unit?->is_published ?? false,
            'generated_by' => $data['generated_by'] ?? $unit?->generated_by ?? 'manual',
            'ai_model' => $data['ai_model'] ?? $unit?->ai_model,
            'created_by' => $unit?->created_by ?? auth()->id(),
        ];

        $unit = $unit ? tap($unit)->update($payload) : CourseUnit::create($payload);

        if (! empty($data['media'] ?? null) && $data['media'] instanceof UploadedFile) {
            $unit->update(['media_path' => $data['media']->store("units/{$unit->id}", 'public')]);
        }

        // Kunlik dars PDF'i — yangisi yuklansa eskisi o'chiriladi
        if (! empty($data['pdf'] ?? null) && $data['pdf'] instanceof UploadedFile) {
            if ($unit->pdf_path) {
                Storage::disk('public')->delete($unit->pdf_path);
            }

            $unit->update(['pdf_path' => $data['pdf']->store("units/{$unit->id}/pdf", 'public')]);
        }

        if (! empty($data['generate_audio']) && filled($unit->media_script) && ! $unit->media_path) {
            $this->generateAudio($unit);
        }

        return $unit->fresh(['teacher.user', 'practiceTest', 'materials']);
    }

    public function delete(CourseUnit $unit): void
    {
        $unit->delete();
    }

    /** media_script → OpenAI TTS audio */
    public function generateAudio(CourseUnit $unit, bool $force = false): ?string
    {
        if (! filled($unit->media_script) || ($unit->media_path && ! $force)) {
            return $unit->media_path;
        }

        $path = $this->speech->synthesize(
            $unit->media_script,
            "units/{$unit->id}",
            null,
            'Speak clearly in natural Italian for language learners at level '.$unit->level.'.',
        );

        if ($path) {
            $unit->update(['media_path' => $path]);
        }

        return $path;
    }

    /**
     * AI orqali darslik mazmunini generatsiya qiladi (bazaga yozilmaydi — o'qituvchi tahrirlaydi).
     *
     * @return array<string, mixed>
     */
    public function generate(array $params): array
    {
        $level = $params['level'];
        $skill = $params['skill'];
        $track = $params['track'] ?? 'general';
        $topic = $params['topic'] ?? null;
        $lang = $params['translation_language'] ?? 'uz';
        $levelTitle = Level::title($level);
        $trackTitle = config("academy.tracks.{$track}", $track);
        $skillTitle = config("academy.skills.{$skill}", $skill);
        $topicLine = $topic ? "Mavzu: {$topic}" : 'Mavzu: darajaga mos';

        $prompt = <<<PROMPT
        Italyan tili darsligi uchun bitta DARS tayyorla.

        Daraja: {$level} — {$levelTitle}
        Yo'nalish: {$trackTitle}
        Ko'nikma: {$skillTitle} ({$skill})
        {$topicLine}
        Tushuntirish tili: {$lang}

        Talablar:
        - `content`: markdown formatida dars matni — qisqa nazariya ({$lang} tilida), italyancha misollar tarjimasi bilan, 8–12 ta yangi so'z (jadval: so'z | tarjima | misol), 3–5 ta mashq ko'rsatmasi.
        - listening/speaking uchun `media_script`: 80–200 so'zli italyancha matn/dialog (audio shundan yaratiladi). Boshqa ko'nikmalarda null.
        - `vocabulary`: [{"word":"…","translation":"…","example":"…"}] — 8–12 ta.
        - `practice_questions`: 5 ta savol (single_choice yoki gap), har birida to'g'ri javob va {$lang} tilida izoh.

        JSON:
        {
          "title": "dars nomi",
          "description": "1–2 gap ({$lang})",
          "content": "markdown",
          "media_script": "… yoki null",
          "vocabulary": [],
          "practice_questions": [
            {"body":"…","type":"single_choice|gap","correct_answer":"…","explanation":"…","options":[{"body":"…","is_correct":true}]}
          ]
        }
        PROMPT;

        $result = [];
        $provider = LocalAi::NAME;
        $model = LocalAi::MODEL;

        if ($this->ai->remoteAvailable('generator')) {
            try {
                $client = $this->ai->for('generator');
                $result = $client->json($prompt, 'Sen italyan tili metodisti va darslik muallifisan. Faqat JSON qaytarasan.', ['temperature' => 0.6, 'max_tokens' => 8000]);
                $provider = $client->name();
                $model = $client->model();
            } catch (\Throwable $e) {
                report($e);
                $result = [];
            }
        }

        if (empty($result['content'])) {
            $result = app(LocalAi::class)->generateUnit($level, $skill, $track, $topic, $lang);
            $provider = LocalAi::NAME;
            $model = LocalAi::MODEL;
        }

        return [
            'level' => $level,
            'track' => $track,
            'skill' => $skill,
            'title' => $result['title'] ?? ($topic ?: "{$level} {$skillTitle}"),
            'description' => $result['description'] ?? null,
            'content' => $result['content'],
            'media_script' => in_array($skill, ['listening', 'speaking'], true) ? ($result['media_script'] ?? null) : null,
            'vocabulary' => $result['vocabulary'] ?? [],
            'practice_questions' => $result['practice_questions'] ?? [],
            'max_plays' => config('academy.defaults.unit_max_plays'),
            'generated_by' => 'ai',
            'ai_model' => $model,
            'provider' => $provider,
        ];
    }

    /* ---------------- Materiallar (video / kitob / fayl) ---------------- */

    public function addMaterial(array $data, ?UploadedFile $file = null): CourseMaterial
    {
        $material = CourseMaterial::create([
            'school_id' => config('school.id'),
            'course_unit_id' => $data['course_unit_id'] ?? null,
            'class_id' => $data['class_id'] ?? null,
            'level' => $data['level'] ?? null,
            'track' => $data['track'] ?? null,
            'type' => $data['type'] ?? ($file ? $this->guessType($file) : 'link'),
            'title' => $data['title'] ?? $file?->getClientOriginalName() ?? 'Material',
            'description' => $data['description'] ?? null,
            'url' => $data['url'] ?? null,
            'max_plays' => $data['max_plays'] ?? null,
            'uploaded_by' => auth()->id(),
        ]);

        if ($file) {
            $material->update([
                'path' => $file->store('materials/'.($material->class_id ? "group-{$material->class_id}" : ($material->level ?: 'common')), 'public'),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        return $material->fresh();
    }

    public function materials(array $filters): Collection
    {
        return CourseMaterial::query()
            ->filter($filters, [
                'course_unit_id' => 'course_unit_id',
                'class_id' => 'class_id',
                'level' => 'level',
                'track' => 'track',
                'type' => 'type',
            ])
            ->orderByDesc('id')
            ->get();
    }

    public function deleteMaterial(CourseMaterial $material): void
    {
        $material->delete();
    }

    /* ---------------- O'quvchi tomoni ---------------- */

    /**
     * O'quvchining darajasi va yo'nalishiga mos darsliklar (progress bilan).
     */
    public function forStudent(Student $student, array $filters = []): Collection
    {
        $level = $filters['level'] ?? $student->effectiveLevel();
        $track = $student->effectiveTrack();

        // Kelajakdagi darslar (available_on > bugun) o'quvchiga ko'rinmaydi
        $units = CourseUnit::published()
            ->available()
            ->forLevel($level, $track)
            ->when($filters['skill'] ?? null, fn ($q, $skill) => $q->where('skill', $skill))
            ->with('practiceTest')
            ->orderBy('order')
            ->get();

        $progress = UnitProgress::where('student_id', $student->id)->whereIn('course_unit_id', $units->pluck('id'))->get()->keyBy('course_unit_id');

        return $units->map(function (CourseUnit $unit) use ($progress, $student) {
            $unit->setRelation('studentProgress', $progress->get($unit->id));
            $unit->setAttribute('play_state', $unit->hasMedia() ? $this->plays->status($student, $unit) : null);

            return $unit;
        });
    }

    /** Darslikni ochish — ko'rishlar soni yoziladi. */
    public function view(Student $student, CourseUnit $unit): CourseUnit
    {
        abort_unless($unit->is_published, 404);
        $this->assertAvailable($unit);

        $progress = UnitProgress::firstOrCreate(
            ['course_unit_id' => $unit->id, 'student_id' => $student->id],
            ['school_id' => config('school.id'), 'first_viewed_at' => now()],
        );

        $progress->increment('views');
        $progress->update(['last_viewed_at' => now()]);

        $unit->load(['practiceTest', 'materials']);
        $unit->setRelation('studentProgress', $progress->fresh());
        $unit->setAttribute('play_state', $unit->hasMedia() ? $this->plays->status($student, $unit) : null);

        return $unit;
    }

    /** Audio/video eshitishni ro'yxatga oladi — limit tugasa 423. */
    public function play(Student $student, CourseUnit $unit): array
    {
        abort_unless($unit->hasMedia(), 404, 'Bu darslikda audio/video yo\'q.');
        $this->assertAvailable($unit);

        return $this->plays->register($student, $unit);
    }

    public function complete(Student $student, CourseUnit $unit): UnitProgress
    {
        $this->assertAvailable($unit);

        return tap(UnitProgress::firstOrCreate(
            ['course_unit_id' => $unit->id, 'student_id' => $student->id],
            ['school_id' => config('school.id')],
        ))->update(['completed_at' => now()]);
    }

    /** Ochilish sanasi kelmagan dars — o'quvchi uchun 403 */
    public function assertAvailable(CourseUnit $unit): void
    {
        abort_unless($unit->isAvailable(), 403, 'Bu dars hali ochilmagan. Ochilish sanasi: '.$unit->available_on?->format('d.m.Y'));
    }

    private function nextOrder(?string $level, string $track): int
    {
        return (int) CourseUnit::where('level', $level)->where('track', $track)->max('order') + 1;
    }

    private function guessType(UploadedFile $file): string
    {
        $mime = (string) $file->getMimeType();

        return match (true) {
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            $mime === 'application/pdf' || $mime === 'application/epub+zip' => 'book',
            default => 'file',
        };
    }
}
