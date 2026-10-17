<?php

namespace App\Events;

use App\Models\Duel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Duel holati o'zgardi — ikkala o'yinchiga WebSocket orqali (Reverb). */
class DuelUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Duel $duel, public string $reason = 'updated') {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('duel.'.$this->duel->id)];
    }

    public function broadcastAs(): string
    {
        return 'duel.updated';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->duel->id, 'status' => $this->duel->status, 'current_no' => $this->duel->current_no, 'reason' => $this->reason];
    }
}
