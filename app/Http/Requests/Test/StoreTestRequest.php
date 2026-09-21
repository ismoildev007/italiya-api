<?php

namespace App\Http\Requests\Test;

use App\Http\Requests\BaseRequest;
use App\Http\Requests\Test\Concerns\HasQuestionRules;

class StoreTestRequest extends BaseRequest
{
    use HasQuestionRules;

    public function rules(): array
    {
        return $this->templateRules('required') + $this->sectionRules() + $this->questionRules();
    }
}
