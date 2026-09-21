<?php

namespace App\Http\Requests\Homework;

use App\Http\Requests\BaseRequest;

class SubmitHomeworkRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'text' => 'nullable|string|max:20000',
            'file' => 'nullable|file|max:51200',
            'audio' => 'nullable|file|extensions:webm,mp3,m4a,wav,ogg,oga,mp4,aac,flac,opus|max:30720',
            'test_attempt_id' => 'nullable|exists:test_attempts,id',
        ];
    }
}
