<?php

namespace App\Events;

use App\Models\Duel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** O'quvchi duelga taklif qilindi — uning shaxsiy kanaliga. */
class DuelInvited implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Duel $duel, public int $studentId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('student.'.$this->studentId)];
    }

    public function broadcastAs(): string
    {
        return 'duel.invited';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->duel->id, 'level' => $this->duel->level, 'invite_code' => $this->duel->invite_code, 'host' => $this->duel->host?->full_name];
    }
}
