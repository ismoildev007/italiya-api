<?php

use App\Models\Duel;
use App\Models\Student;
use Illuminate\Support\Facades\Broadcast;

/*
| WebSocket kanallari (Reverb). Avtorizatsiya: Sanctum token (Bearer).
|   student.{id} — o'quvchining shaxsiy kanali (duel taklifi)
|   duel.{id}    — duel ishtirokchilari
*/

Broadcast::channel('student.{id}', function ($user, $id) {
    return (int) Student::where('user_id', $user->id)->value('id') === (int) $id;
});

Broadcast::channel('duel.{id}', function ($user, $id) {
    $studentId = Student::where('user_id', $user->id)->value('id');
    $duel = Duel::find($id);

    return $duel && $studentId && in_array($studentId, [$duel->host_student_id, $duel->opponent_student_id], true);
});
