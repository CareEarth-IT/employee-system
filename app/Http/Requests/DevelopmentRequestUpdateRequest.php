<?php

namespace App\Http\Requests;

use App\Models\DevelopmentRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DevelopmentRequestUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canEditDevelopmentRequest();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $labels = array_values(DevelopmentRequest::CONTENT_TYPE_LABELS);

        return [
            'progress' => ['required', 'string', Rule::in(DevelopmentRequest::PROGRESS_OPTIONS)],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0'],
            'actual_hours' => ['nullable', 'numeric', 'min:0'],
            'development_target_date' => ['nullable', 'date'],
            'development_assignee' => ['required', 'string', Rule::in(DevelopmentRequest::DEV_ASSIGNEE_OPTIONS)],
            'content_type_label' => ['required', 'string', Rule::in($labels)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'progress' => '進捗',
            'remarks' => '備考',
            'estimated_hours' => '予想工数(h)',
            'actual_hours' => '実工数(h)',
            'development_target_date' => '開発終了目標',
            'development_assignee' => '開発担当',
            'content_type_label' => 'Type',
        ];
    }
}
