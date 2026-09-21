<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Zoom / Google Meet onlayn darslar. */
class MeetingService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return Meeting::query()
            ->with(['teacher.user', 'groups'])
            ->search($filters['search'] ?? null, ['title'])
            ->filter($filters, [
                'teacher_id' => 'teacher_id',
                'status' => 'status',
                'class_id' => fn ($q, $id) => $q->whereHas('groups', fn ($g) => $g->where('class_id', $id)),
                'from' => ['starts_at', 'date_from'],
                'to' => ['starts_at', 'date_to'],
                'upcoming' => fn ($q) => $q->upcoming(),
            ])
            ->orderBy('starts_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function save(array $data, ?Meeting $meeting = null): Meeting
    {
        return DB::transaction(function () use ($data, $meeting) {
            $payload = [
                'school_id' => config('school.id'),
                'teacher_id' => $data['teacher_id'] ?? $meeting?->teacher_id ?? Teacher::where('user_id', auth()->id())->value('id'),
                'title' => $data['title'] ?? $meeting?->title,
                'description' => $data['description'] ?? $meeting?->description,
                'platform' => $data['platform'] ?? $meeting?->platform ?? 'zoom',
                'link' => $data['link'] ?? $meeting?->link ?? 'https://zoom.us',
                'starts_at' => $data['starts_at'] ?? $meeting?->starts_at,
                'duration_minutes' => $data['duration_minutes'] ?? $meeting?->duration_minutes ?? 60,
                'levels' => $data['levels'] ?? $meeting?->levels,
                'tracks' => $data['tracks'] ?? $meeting?->tracks,
                'status' => $data['status'] ?? $meeting?->status ?? 'scheduled',
                'created_by' => $meeting?->created_by ?? auth()->id(),
            ];

            $isNew = $meeting === null;
            $meeting = $meeting ? tap($meeting)->update($payload) : Meeting::create($payload);

            if (array_key_exists('class_ids', $data)) {
                $meeting->groups()->sync($data['class_ids'] ?? []);
            }

            $meeting = $meeting->fresh(['teacher.user', 'groups']);

            if ($isNew) {
                $this->notifications->meetingScheduled($meeting, $this->audience($meeting));
            }

            return $meeting;
        });
    }

    public function delete(Meeting $meeting): void
    {
        $meeting->delete();
    }

    /** O'quvchiga ko'rinadigan (guruhi, darajasi yoki yo'nalishi bo'yicha) kelgusi darslar. */
    public function forStudent(Student $student, bool $upcomingOnly = true): Collection
    {
        return Meeting::with(['teacher.user', 'groups'])
            ->when($upcomingOnly, fn ($q) => $q->upcoming())
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (Meeting $m) => $m->isVisibleTo($student))
            ->values();
    }

    /** Mitingga tegishli barcha faol o'quvchilar. */
    public function audience(Meeting $meeting): Collection
    {
        return Student::active()
            ->where(function ($q) use ($meeting) {
                $q->whereIn('class_id', $meeting->groups->pluck('id'))
                    ->orWhereIn('level', $meeting->levels ?? [])
                    ->orWhereIn('track', $meeting->tracks ?? []);
            })
            ->get();
    }
}
