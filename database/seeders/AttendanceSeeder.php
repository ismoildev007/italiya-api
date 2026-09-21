<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\NotificationEvent;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\Data\SchoolCalendar;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Davomat — hozirgi bosqichda sinf rahbari/o'qituvchi tomonidan QO'LDA belgilanadi,
 * shuning uchun barcha yozuvlarning `source` maydoni `manual`.
 * `device` manbai kelajakdagi Face ID / turniket integratsiyasi uchun ajratilgan.
 */
class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(555);

        $year = AcademicYear::active()->firstOrFail();
        $days = SchoolCalendar::schoolDays($year->starts_on->copy(), now());

        if (empty($days)) {
            $this->command?->warn('O\'quv yili hali boshlanmagan — davomat yaratilmadi.');

            return;
        }

        $students = Student::with('schoolClass.classTeacher')->orderBy('id')->get();

        $rows = [];
        $events = [];
        $stats = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];

        // Sinf rahbarlarining user_id lari — kim belgilagani
        $markedBy = User::whereHas('teacher')->pluck('id', 'id');

        foreach ($days as $dayIndex => $day) {
            foreach ($students as $index => $student) {
                $roll = ($index * 7 + $dayIndex * 13) % 100;

                $status = match (true) {
                    $roll < 86 => Attendance::STATUS_PRESENT,
                    $roll < 93 => Attendance::STATUS_ABSENT,
                    $roll < 98 => Attendance::STATUS_LATE,
                    default => Attendance::STATUS_EXCUSED,
                };

                $stats[$status]++;

                $teacherUserId = $student->schoolClass?->classTeacher?->user_id
                    ?? $markedBy->first();

                $rows[] = [
                    'school_id' => config('school.id'),
                    'student_id' => $student->id,
                    'class_id' => $student->class_id,
                    'date' => $day->toDateString(),
                    'status' => $status,
                    'source' => Attendance::SOURCE_MANUAL,
                    'device_id' => null,
                    'marked_at' => $day->copy()->setTime(8, 15)->toDateTimeString(),
                    'marked_by' => $teacherUserId,
                    'note' => $status === Attendance::STATUS_EXCUSED ? 'Shifokor ma\'lumotnomasi taqdim etilgan' : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Ota-onaga xabar faqat kelmagan/kech qolgan holatlarda
                if (in_array($status, [Attendance::STATUS_ABSENT, Attendance::STATUS_LATE], true)) {
                    $events[] = [
                        'student_id' => $student->id,
                        'date' => $day->toDateString(),
                        'status' => $status,
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('attendances')->insert($chunk);
        }

        $this->seedNotifications($events);

        $total = count($rows);
        $this->command?->info("✅ {$total} ta davomat yozuvi (".count($days)." o'quv kuni, manba: manual).");
        $this->command?->line("   • Keldi: {$stats['present']}, kelmadi: {$stats['absent']}, kech qoldi: {$stats['late']}, sababli: {$stats['excused']}");
    }

    /** Davomat bo'yicha Telegram hodisalari (stub) — faqat oxirgi 3 kun uchun. */
    private function seedNotifications(array $events): void
    {
        $recent = collect($events)->sortByDesc('date')->take(150);

        $guardians = DB::table('guardian_student')
            ->join('guardians', 'guardians.id', '=', 'guardian_student.guardian_id')
            ->select('guardian_student.student_id', 'guardians.id as guardian_id', 'guardians.telegram_chat_id')
            ->where('guardian_student.is_primary', true)
            ->get()
            ->keyBy('student_id');

        $rows = [];

        foreach ($recent as $event) {
            $guardian = $guardians->get($event['student_id']);

            if (! $guardian) {
                continue;
            }

            $label = $event['status'] === Attendance::STATUS_ABSENT ? 'darsga kelmadi' : 'darsga kech qoldi';

            $rows[] = [
                'school_id' => config('school.id'),
                'type' => NotificationEvent::TYPE_ATTENDANCE,
                'channel' => 'telegram',
                'recipient_type' => 'guardian',
                'recipient_id' => $guardian->guardian_id,
                'recipient_address' => $guardian->telegram_chat_id,
                'student_id' => $event['student_id'],
                'source_type' => null,
                'source_id' => null,
                'title' => 'Davomat',
                'message' => "Farzandingiz {$event['date']} kuni {$label}.",
                'payload' => json_encode(['status' => $event['status'], 'date' => $event['date']]),
                'status' => NotificationEvent::STATUS_PENDING,
                'attempts' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('notification_events')->insert($chunk);
        }
    }
}
