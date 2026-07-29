<?php

namespace App\Http\Requests;

use App\Models\AttendanceNotification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceNotificationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->canViewAttendanceSection();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = $this->input('notification_type');

        return [
            'notification_type' => ['required', 'string', Rule::in(array_keys(AttendanceNotification::TYPE_LABELS))],
            'target_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:target_date'],
            'scheduled_time' => [
                'nullable',
                Rule::requiredIf(in_array($type, [AttendanceNotification::TYPE_LATE, AttendanceNotification::TYPE_EARLY_LEAVE], true)),
                'date_format:H:i',
            ],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'notification_type.required' => '連絡種別を選択してください。',
            'notification_type.in' => '連絡種別が不正です。',
            'target_date.required' => '対象日を入力してください。',
            'target_date.date' => '対象日は正しい日付形式で入力してください。',
            'end_date.date' => '終了日は正しい日付形式で入力してください。',
            'end_date.after_or_equal' => '終了日は対象日以降の日付を入力してください。',
            'scheduled_time.required' => '予定時刻を入力してください。',
            'scheduled_time.date_format' => '予定時刻は HH:MM 形式で入力してください。',
            'reason.required' => '理由を入力してください。',
            'reason.max' => '理由は2000文字以内で入力してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'notification_type' => '連絡種別',
            'target_date' => '対象日',
            'end_date' => '終了日',
            'scheduled_time' => '予定時刻',
            'reason' => '理由',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);

        if (! in_array($validated['notification_type'] ?? '', [AttendanceNotification::TYPE_LATE, AttendanceNotification::TYPE_EARLY_LEAVE], true)) {
            $validated['scheduled_time'] = null;
        }

        if (empty($validated['end_date'])) {
            $validated['end_date'] = null;
        }

        return $validated;
    }
}
