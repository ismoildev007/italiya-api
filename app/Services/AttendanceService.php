<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Davomat servisi.
 *
 * Hozircha davomat FAQAT qo'lda belgilanadi (`source = manual`).
 * Kelajakda Face ID / turniket qo'shilganda faqat yangi manba
 * (`source = device`) bilan yozuv keladi — bu servis va `attendances`
 * jadvali strukturasi o'zgarmaydi.
 */
class AttendanceService
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function paginate(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return Attendance::query()
            ->with(['student', 'markedBy'])
            ->filter($filters, [
                'class_id' => 'class_id',
                'student_id' => 'student_id',
                'status' => 'status',
                'source' => 'source',
                'date' => ['date', 'date'],
                'from' => ['date', 'date_from'],
                'to' => ['date', 'date_to'],
            ])
            ->orderByDesc('date')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** Yozuvni qo'lda tuzatish — manba doim `manual` bo'lib qoladi. */
    public function updateRecord(Attendance $attendance, array $data): Attendance
    {
        $attendance->update([
            ...$data,
            'source' => Attendance::SOURCE_MANUAL,
            'marked_by' => auth()->id(),
            'marked_at' => now(),
        ]);

        return $attendance->fresh('student');
    }

    public function deleteRecord(Attendance $attendance): void
    {
        $attendance->delete();
    }

    /**
     * Sinf jurnali: bir kunlik ro'yxat (barcha o'quvchilar + belgilangan status).
     */
    public function classJournal(SchoolClass $class, Carbon $date): array
    {
        $students = Student::where('class_id', $class->id)
            ->active()
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get();

        $records = Attendance::where('class_id', $class->id)
            ->whereDate('date', $date->toDateString())
            ->get()
            ->keyBy('student_id');

        return [
            'class' => $class->only(['id', 'name', 'grade', 'letter', 'shift']),
            'date' => $date->toDateString(),
            'students' => $students->map(function (Student $student) use ($records) {
                $record = $records->get($student->id);

                return [
                    'student_id' => $student->id,
                    'student_no' => $student->student_no,
                    'full_name' => $student->full_name,
                    'status' => $record?->status,
                    'source' => $record?->source,
                    'note' => $record?->note,
                    'marked' => (bool) $record,
                ];
            })->values(),
            'summary' => $this->summarize($records),
        ];
    }

    /**
     * Jurnalni to'plamli saqlash — "tez belgilash" interfeysi uchun.
     *
     * @param  array<int, array{student_id:int, status:string, note?:string}>  $rows
     */
    public function bulkMark(SchoolClass $class, Carbon $date, array $rows, ?int $markedBy = null): array
    {
        $saved = 0;
        $changed = [];

        DB::transaction(function () use ($class, $date, $rows, $markedBy, &$saved, &$changed) {
            foreach ($rows as $row) {
                $existing = Attendance::where('student_id', $row['student_id'])
                    ->whereDate('date', $date->toDateString())
                    ->first();

                $attendance = Attendance::updateOrCreate(
                    [
                        'student_id' => $row['student_id'],
                        'date' => $date->toDateString(),
                    ],
                    [
                        'school_id' => config('school.id'),
                        'class_id' => $class->id,
                        'status' => $row['status'],
                        'source' => Attendance::SOURCE_MANUAL,
                        'marked_at' => now(),
                        'marked_by' => $markedBy ?? auth()->id(),
                        'note' => $row['note'] ?? null,
                    ]
                );

                $saved++;

                // Status o'zgargan bo'lsa — ota-onaga xabar hodisasi
                if (! $existing || $existing->status !== $attendance->status) {
                    $changed[] = $attendance;
                }
            }
        });

        foreach ($changed as $attendance) {
            $this->notifications->attendanceMarked($attendance->load('student'));
        }

        return ['saved' => $saved, 'notified' => count($changed)];
    }

    public function mark(Student $student, Carbon $date, string $status, ?string $note = null): Attendance
    {
        $attendance = Attendance::updateOrCreate(
            ['student_id' => $student->id, 'date' => $date->toDateString()],
            [
                'school_id' => config('school.id'),
                'class_id' => $student->class_id,
                'status' => $status,
                'source' => Attendance::SOURCE_MANUAL,
                'marked_at' => now(),
                'marked_by' => auth()->id(),
                'note' => $note,
            ]
        );

        $this->notifications->attendanceMarked($attendance->load('student'));

        return $attendance;
    }

    /**
     * O'quvchining davomat tarixi + foizli statistika.
     */
    public function studentHistory(Student $student, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $query = Attendance::where('student_id', $student->id)
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to->toDateString()))
            ->orderByDesc('date');

        $records = $query->get();

        return [
            'records' => $records,
            'summary' => $this->summarize($records),
        ];
    }

    /**
     * @param  Collection<int, Attendance>  $records
     */
    public function summarize(Collection $records): array
    {
        $total = $records->count();

        $counts = [
            Attendance::STATUS_PRESENT => 0,
            Attendance::STATUS_ABSENT => 0,
            Attendance::STATUS_LATE => 0,
            Attendance::STATUS_EXCUSED => 0,
        ];

        foreach ($records as $record) {
            if (isset($counts[$record->status])) {
                $counts[$record->status]++;
            }
        }

        return [
            'total' => $total,
            'present' => $counts[Attendance::STATUS_PRESENT],
            'absent' => $counts[Attendance::STATUS_ABSENT],
            'late' => $counts[Attendance::STATUS_LATE],
            'excused' => $counts[Attendance::STATUS_EXCUSED],
            'attendance_rate' => $total > 0
                ? round(($counts[Attendance::STATUS_PRESENT] + $counts[Attendance::STATUS_LATE]) / $total * 100, 2)
                : 0.0,
        ];
    }
}
