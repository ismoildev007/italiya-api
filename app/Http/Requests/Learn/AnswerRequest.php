<?php

namespace App\Http\Requests\Learn;

use App\Http\Requests\BaseRequest;

/** Bitta savolga javob (autosave). Ovozli javob — `audio` fayl (OpenAI STT). */
class AnswerRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'question_id' => 'required|integer',
            'selected_option_ids' => 'nullable|array',
            'selected_option_ids.*' => 'integer',
            'answer_text' => 'nullable|string|max:20000',
            'transcript' => 'nullable|string|max:20000',
            'option_order' => 'nullable|array',
            'audio' => 'nullable|file|extensions:webm,mp3,m4a,wav,ogg,oga,mp4,aac,flac,opus|max:30720',
        ];
    }
}
