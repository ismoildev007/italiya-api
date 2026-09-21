<?php

namespace Database\Seeders;

use App\Models\CourseMaterial;
use App\Models\CourseUnit;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TestTemplate;
use Database\Seeders\Data\ItalianContent;
use Illuminate\Database\Seeder;

/**
 * Darsliklar: har bir daraja uchun 4 ko'nikma bo'yicha dars (audio limiti bilan) + guruh kutubxonasi.
 */
class CourseUnitSeeder extends Seeder
{
    public function run(): void
    {
        $subjectId = Subject::where('code', 'ITA')->value('id');
        $teacherByLevel = SchoolClass::get()->groupBy('level')->map(fn ($g) => $g->first()->class_teacher_id);
        $count = 0;

        foreach (ItalianContent::levels() as $level => $c) {
            $practice = TestTemplate::where('level', $level)->where('kind', 'practice')->value('id');
            $order = 1;

            $units = [
                ['listening', "Ascolto: {$c['topic']}", $c['listening']['script'], "## Tinglash\n\nAudioni diqqat bilan tinglang (maksimal 3 marta). Asosiy ma'lumotlarni yozib oling: kim, qayerda, qachon.\n\n**Foydali iboralar:**\n- *Scusi, può ripetere?* — Kechirasiz, takrorlay olasizmi?\n- *Non ho capito.* — Tushunmadim."],
                ['reading', "Lettura: {$c['reading']['title']}", null, "## O'qish\n\n{$c['reading']['passage']}\n\n---\n**Mashq:** matndagi yangi so'zlarni belgilang va tarjima qiling."],
                ['writing', "Scrittura: {$c['topic']}", null, "## Yozish\n\nNamunaviy javob:\n\n> {$c['writing']['essay'][1]}\n\n**Tuzilma:** kirish → asosiy qism → xulosa. Bog'lovchilar: *inoltre, tuttavia, quindi, infine*."],
                ['speaking', "Parlato: {$c['topic']}", implode(' ', array_map(fn ($s) => $s[0].' '.$s[1], $c['speaking'])), "## Gapirish\n\nSavollar va namunaviy javoblar:\n\n".implode("\n", array_map(fn ($s) => "- **{$s[0]}** — {$s[1]}", $c['speaking']))."\n\nAudioni tinglab, takrorlang va mikrofonga yozib ko'ring."],
                ['grammar', "Grammatica {$level}", null, "## Grammatika\n\nBo'shliqlarni to'ldiring:\n\n".implode("\n", array_map(fn ($g) => "- {$g[0]} (*{$g[1]}*)", $c['writing']['gaps']))],
            ];

            foreach ($units as [$skill, $title, $script, $content]) {
                CourseUnit::create([
                    'school_id' => config('school.id'),
                    'subject_id' => $subjectId,
                    'teacher_id' => $teacherByLevel->get($level),
                    'level' => $level,
                    'track' => 'general',
                    'order' => $order++,
                    'skill' => $skill,
                    'title' => $title,
                    'description' => "{$level} darajasi, «{$c['topic']}» mavzusi — ".config("academy.skills.{$skill}"),
                    'content' => $content,
                    'media_script' => $script,
                    'max_plays' => $script ? ($skill === 'listening' ? 3 : 5) : null,
                    'time_limit_minutes' => $skill === 'reading' ? 20 : null,
                    'test_template_id' => in_array($skill, ['listening', 'reading'], true) ? $practice : null,
                    'is_published' => true,
                    'generated_by' => in_array($skill, ['listening', 'reading'], true) ? 'ai' : 'manual',
                    'ai_model' => in_array($skill, ['listening', 'reading'], true) ? config('services.gemini.model') : null,
                ]);
                $count++;
            }
        }

        // Guruh kutubxonasi: video darslik, kitob, havola (mockupdagi 3 ustunli panel)
        $materials = 0;
        foreach (SchoolClass::get() as $class) {
            foreach ([
                ['video', "Video dars: {$class->level} grammatika", 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 2],
                ['book', "Kitob: Nuovo Espresso {$class->level} (PDF)", null, null],
                ['link', 'Onlayn lug\'at — Treccani', 'https://www.treccani.it/vocabolario/', null],
            ] as [$type, $title, $url, $maxPlays]) {
                CourseMaterial::create([
                    'school_id' => config('school.id'),
                    'class_id' => $class->id,
                    'level' => $class->level,
                    'track' => $class->track,
                    'type' => $type,
                    'title' => $title,
                    'url' => $url,
                    'path' => $type === 'book' ? "materials/group-{$class->id}/nuovo-espresso-{$class->level}.pdf" : null,
                    'mime' => $type === 'book' ? 'application/pdf' : null,
                    'max_plays' => $maxPlays,
                ]);
                $materials++;
            }
        }

        $this->command?->info("✅ {$count} ta darslik (audio limiti bilan) va {$materials} ta guruh materiali yaratildi.");
    }
}
