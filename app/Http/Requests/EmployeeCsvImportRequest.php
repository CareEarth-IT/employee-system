<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeCsvImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isInformationSystems();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'csv.required' => 'CSVファイルを選択してください。',
            'csv.file' => 'CSVファイルを選択してください。',
            'csv.mimes' => 'CSV形式のファイルをアップロードしてください。',
            'csv.max' => 'CSVファイルは5MB以下にしてください。',
        ];
    }
}
