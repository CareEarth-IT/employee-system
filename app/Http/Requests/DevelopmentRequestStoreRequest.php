<?php

namespace App\Http\Requests;

use App\Models\DevelopmentRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DevelopmentRequestStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'request_date' => ['required', 'date'],
            'content_type' => ['required', 'string', Rule::in(DevelopmentRequest::CONTENT_TYPE_OPTIONS)],
            'title' => ['required', 'string', 'max:30'],
            'purpose' => ['required', 'string', 'max:5000'],
            'detail' => ['required', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'request_date' => '依頼日',
            'content_type' => '依頼内容について',
            'title' => '依頼内容タイトル',
            'purpose' => '目的 (改善内容)',
            'detail' => '依頼内容詳しく',
        ];
    }
}
