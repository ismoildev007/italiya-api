<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentClassHistory;
use App\Models\StudentLevelHistory;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StudentService
{
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return Student::query()
            ->with(['schoolClass', 'activeTariff.tariff', 'activeDiscount', 'balance'])
            ->search($filters['search'] ?? null, ['firstname', 'lastname', 'middlename', 'student_no', 'phone'])
            ->filter($filters, [
                'class_id' => 'class_id',
                'level' => 'level',
                'track' => 'track',
                'grade' => ['schoolClass.grade', 'has'],
                'status' => 'status',
                'tariff_id' => fn ($q, $value) => $q->whereHas(
                    'tariffs', fn ($q) => $q->where('tariff_id', $value)->where('is_active', true)
                ),
                'debtors' => fn ($q) => $q->whereHas('balance', fn ($q) => $q->where('balance', '<', 0)),
            ])
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): Student
    {
        return DB::transaction(function () use ($data) {
            $class = ! empty($data['class_id']) ? SchoolClass::find($data['class_id']) : null;

            $student = Student::create([
                'school_id' => config('school.id'),
                'class_id' => $data['class_id'] ?? null,
                'level' => $data['level'] ?? $class?->level,
                'track' => $data['track'] ?? $class?->track ?? 'general',
                'monthly_hours' => $data['monthly_hours'] ?? 0,
                'preferred_language' => $data['preferred_language'] ?? config('academy.default_translation_language'),
                'student_no' => $data['student_no'] ?? $this->nextStudentNo(),
                'firstname' => $data['firstname'],
                'lastname' => $data['lastname'],
                'middlename' => $data['middlename'] ?? null,
                'birth_date' => $data['birth_date'] ?? null,
                'gender' => $data['gender'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'photo' => $data['photo'] ?? null,
                'face_id' => $data['face_id'] ?? null,
                'card_id' => $data['card_id'] ?? null,
                'enrolled_at' => $data['enrolled_at'] ?? now()->toDateString(),
                'status' => $data['status'] ?? 'active',
                'notes' => $data['notes'] ?? null,
            ]);

            // O'quvchi uchun akkaunt (ixtiyoriy) — `student` roli, faqat o'qish huquqi
            if (! empty($data['create_account'])) {
                $student->user_id = $this->createAccount($student, $data['password'] ?? null)->id;
                $student->save();
            }

            if ($student->class_id) {
                $this->recordClassHistory($student, $student->class_id, 'enrolled');
            }

            if ($student->level) {
                $this->recordLevelHistory($student, null, $student->level, 'enrolled');
            }

            if (! empty($data['parent_phone'])) {
                $this->linkParentByPhone($student, $data);
            }

            return $student->load(['schoolClass', 'user', 'guardians']);
        });
    }

    public function update(Student $student, array $data): Student
    {
        return DB::transaction(function () use ($student, $data) {
            $previousClass = $student->class_id;
            $previousLevel = $student->level;

            $student->update(array_filter([
                'class_id' => $data['class_id'] ?? null,
                'level' => $data['level'] ?? null,
                'track' => $data['track'] ?? null,
                'monthly_hours' => $data['monthly_hours'] ?? null,
                'preferred_language' => $data['preferred_language'] ?? null,
                'student_no' => $data['student_no'] ?? null,
                'firstname' => $data['firstname'] ?? null,
                'lastname' => $data['lastname'] ?? null,
                'middlename' => $data['middlename'] ?? null,
                'birth_date' => $data['birth_date'] ?? null,
                'gender' => $data['gender'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'photo' => $data['photo'] ?? null,
                'face_id' => $data['face_id'] ?? null,
                'card_id' => $data['card_id'] ?? null,
                'enrolled_at' => $data['enrolled_at'] ?? null,
                'status' => $data['status'] ?? null,
                'notes' => $data['notes'] ?? null,
            ], fn ($v) => $v !== null));

            if (! empty($data['class_id']) && $data['class_id'] !== $previousClass) {
                $this->closeClassHistory($student, $previousClass);
                $this->recordClassHistory($student, $data['class_id'], 'transferred');
            }

            if (! empty($data['level']) && $data['level'] !== $previousLevel) {
                $this->recordLevelHistory($student, $previousLevel, $data['level'], 'manual');
                app(NotificationService::class)->levelChanged($student, $previousLevel, $data['level']);
            }

            if (! empty($data['parent_phone'])) {
                $this->linkParentByPhone($student, $data);
            }

            return $student->fresh(['schoolClass', 'user', 'guardians']);
        });
    }

    /**
     * Sinfni o'zgartirish — eski yozuv arxivlanadi, yo'qotilmaydi.
     */
    public function transfer(Student $student, SchoolClass $class, ?string $reason = 'promoted'): Student
    {
        return DB::transaction(function () use ($student, $class, $reason) {
            $this->closeClassHistory($student, $student->class_id);

            $student->update(['class_id' => $class->id]);

            $this->recordClassHistory($student, $class->id, $reason);

            return $student->fresh('schoolClass');
        });
    }

    /**
     * Darajani o'zgartirish (A1 → A2 …) — tarix yoziladi, ota-ona va o'quvchiga xabar boradi.
     */
    public function changeLevel(Student $student, string $level, ?string $reason = 'promoted'): Student
    {
        $previous = $student->level;

        if ($previous === $level) {
            return $student;
        }

        $student->update(['level' => $level]);
        $this->recordLevelHistory($student, $previous, $level, $reason);
        app(NotificationService::class)->levelChanged($student, $previous, $level);

        return $student->fresh(['schoolClass', 'levelHistory']);
    }

    /** Qurilma bog'lanishini tozalash — o'quvchi boshqa telefondan kira oladi. */
    public function resetDevice(Student $student): Student
    {
        $student->update(['device_id' => null, 'device_bound_at' => null]);

        return $student->fresh();
    }

    /**
     * Ota-ona telefon raqami bo'yicha bog'lash: raqam mavjud bo'lsa — biriktiriladi,
     * bo'lmasa yangi ota-ona yoziladi (Telegram bog'lash kodi bilan).
     */
    public function linkParentByPhone(Student $student, array $data): Guardian
    {
        $phone = trim($data['parent_phone']);

        $guardian = Guardian::where('phone', $phone)->first()
            ?? Guardian::create([
                'school_id' => config('school.id'),
                'firstname' => $data['parent_firstname'] ?? 'Ota-ona',
                'lastname' => $data['parent_lastname'] ?? $student->lastname,
                'phone' => $phone,
                'link_code' => strtoupper(Str::random(8)),
            ]);

        $this->attachGuardian($student, $guardian->id, $data['parent_relation'] ?? 'father', ! $student->guardians()->exists());

        return $guardian;
    }

    private function recordLevelHistory(Student $student, ?string $from, string $to, ?string $reason): void
    {
        StudentLevelHistory::create([
            'school_id' => config('school.id'),
            'student_id' => $student->id,
            'from_level' => $from,
            'to_level' => $to,
            'reason' => $reason,
            'changed_by' => auth()->id(),
            'changed_at' => now(),
        ]);
    }

    public function attachGuardian(Student $student, int $guardianId, string $relation, bool $isPrimary = false): void
    {
        $student->guardians()->syncWithoutDetaching([
            $guardianId => [
                'school_id' => config('school.id'),
                'relation' => $relation,
                'is_primary' => $isPrimary,
            ],
        ]);
    }

    public function delete(Student $student): void
    {
        $student->delete();
    }

    /** Batafsil ko'rinish uchun barcha bog'lanishlar. */
    public function withDetails(Student $student): Student
    {
        return $student->load([
            'schoolClass.classTeacher.user',
            'user.roles',
            'guardians',
            'skillProgress',
            'levelHistory',
            'activeTariff.tariff',
            'activeDiscount',
            'balance',
        ]);
    }

    /**
     * Sinf tarixi (arxiv) — eng yangisidan boshlab.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function classHistory(Student $student): Collection
    {
        return $student->classHistory()
            ->with(['schoolClass', 'academicYear'])
            ->orderByDesc('from_date')
            ->get()
            ->map(fn (StudentClassHistory $item) => [
                'class' => $item->schoolClass?->name,
                'academic_year' => $item->academicYear?->name,
                'from_date' => $item->from_date?->toDateString(),
                'to_date' => $item->to_date?->toDateString(),
                'reason' => $item->reason,
            ]);
    }

    /**
     * To'liq moliyaviy tarix — hech narsa yashirin qolmaydi.
     */
    public function financeHistory(Student $student): array
    {
        return [
            'balance' => $this->balanceSummary($student),
            'transactions' => $student->transactions()->with('author')->orderByDesc('id')->get(),
        ];
    }

    /** Balans xulosasi — bir nechta joyda takrorlanadigan format. */
    public function balanceSummary(Student $student): ?array
    {
        $balance = $student->balance;

        return $balance ? [
            'balance' => (float) $balance->balance,
            'total_charged' => (float) $balance->total_charged,
            'total_paid' => (float) $balance->total_paid,
            'is_debtor' => $balance->isDebtor(),
        ] : null;
    }

    public function nextStudentNo(): string
    {
        $year = AcademicYear::active()->value('starts_on')
            ? AcademicYear::active()->first()->starts_on->year
            : now()->year;

        $last = Student::withTrashed()->where('student_no', 'like', "{$year}-%")
            ->orderByDesc('student_no')
            ->value('student_no');

        $next = $last ? ((int) Str::afterLast($last, '-')) + 1 : 1;

        return sprintf('%d-%04d', $year, $next);
    }

    private function createAccount(Student $student, ?string $password): User
    {
        $user = User::create([
            'school_id' => config('school.id'),
            'firstname' => $student->firstname,
            'lastname' => $student->lastname,
            'middlename' => $student->middlename,
            'username' => 'o'.$student->student_no,
            'phone' => $student->phone,
            'password' => $password ?: Str::random(10),
            'status' => 'active',
            'locale' => config('school.locale'),
        ]);

        $user->roles()->sync(['student']);

        return $user;
    }

    private function recordClassHistory(Student $student, int $classId, ?string $reason): void
    {
        $yearId = SchoolClass::whereKey($classId)->value('academic_year_id')
            ?? AcademicYear::active()->value('id');

        StudentClassHistory::updateOrCreate(
            [
                'student_id' => $student->id,
                'class_id' => $classId,
                'academic_year_id' => $yearId,
            ],
            [
                'school_id' => config('school.id'),
                'from_date' => now()->toDateString(),
                'to_date' => null,
                'reason' => $reason,
            ]
        );
    }

    private function closeClassHistory(Student $student, ?int $classId): void
    {
        if (! $classId) {
            return;
        }

        StudentClassHistory::where('student_id', $student->id)
            ->where('class_id', $classId)
            ->whereNull('to_date')
            ->update(['to_date' => now()->toDateString()]);
    }
}
