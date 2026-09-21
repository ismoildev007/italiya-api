<?php

namespace App\Http\Resources;

use App\Support\Level;
use Illuminate\Http\Request;

class StudentResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_no' => $this->student_no,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'middlename' => $this->middlename,
            'full_name' => $this->full_name,
            'birth_date' => $this->date($this->birth_date),
            'gender' => $this->gender,
            'phone' => $this->phone,
            'address' => $this->address,
            'photo' => $this->photo,
            'face_id' => $this->face_id,
            'card_id' => $this->card_id,
            'enrolled_at' => $this->date($this->enrolled_at),
            'status' => $this->status,
            'notes' => $this->notes,
            'level' => $this->level,
            'level_title' => Level::title($this->level),
            'track' => $this->track,
            'track_title' => config("academy.tracks.{$this->track}", $this->track),
            'monthly_hours' => $this->monthly_hours,
            'preferred_language' => $this->preferred_language,
            'telegram_linked' => (bool) $this->telegram_chat_id,
            'device_bound' => (bool) $this->device_id,
            'device_bound_at' => $this->datetime($this->device_bound_at),
            'speech_game_score' => $this->speech_game_score,
            'streak_days' => $this->streak_days,
            'last_activity_at' => $this->datetime($this->last_activity_at),
            'class' => new SchoolClassResource($this->whenLoaded('schoolClass')),
            'group' => new SchoolClassResource($this->whenLoaded('schoolClass')),
            'skills' => $this->whenLoaded('skillProgress', fn () => $this->skillProgress->map(fn ($s) => [
                'level' => $s->level, 'skill' => $s->skill, 'percent' => (float) $s->percent, 'points' => $s->points,
            ])->values()),
            'certificates_count' => $this->whenCounted('certificates'),
            'user' => new UserResource($this->whenLoaded('user')),
            'guardians' => GuardianResource::collection($this->whenLoaded('guardians')),
            'tariff' => new StudentTariffResource($this->whenLoaded('activeTariff')),
            'discount' => new StudentDiscountResource($this->whenLoaded('activeDiscount')),
            'balance' => $this->whenLoaded('balance', fn () => [
                'balance' => (float) $this->balance->balance,
                'total_charged' => (float) $this->balance->total_charged,
                'total_paid' => (float) $this->balance->total_paid,
                'is_debtor' => $this->balance->isDebtor(),
            ]),
            'created_at' => $this->datetime($this->created_at),
        ];
    }
}
