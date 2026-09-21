<?php

namespace App\Http\Requests\Learn;

use App\Http\Requests\BaseRequest;

class GameAnswerRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'round_no' => 'required|integer|min:1',
            'answer' => 'nullable|string|max:500',
            'audio' => 'nullable|file|extensions:webm,mp3,m4a,wav,ogg,oga,mp4,aac,flac,opus|max:30720',
        ];
    }
}
