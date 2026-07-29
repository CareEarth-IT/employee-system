<?php

namespace App\Http\Requests;

use App\Support\DashboardTab;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $department = (string) $this->input('department', '');

        return $department !== ''
            && ($this->user()?->canEditDashboardDepartment($department) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'department' => ['required', 'string', Rule::in(DashboardTab::departmentOptions())],
            'content_html' => ['required', 'string'],
            'page_url' => ['nullable', 'string', 'max:2048'],
            'is_visible' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_visible' => $this->boolean('is_visible'),
        ]);
    }
}
