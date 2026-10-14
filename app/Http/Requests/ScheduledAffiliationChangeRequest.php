<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesAffiliationOrgFields;
use App\Models\ScheduledAffiliationChange;
use App\Models\User;
use App\Support\RegistryOrgAssignment;
use App\Support\ScheduledAffiliationChangeDiff;
use Illuminate\Foundation\Http\FormRequest;

class ScheduledAffiliationChangeRequest extends FormRequest
{
    use ValidatesAffiliationOrgFields;

    public function authorize(): bool
    {
        return auth()->user()?->canScheduleAffiliationChange() === true;
    }

    protected function prepareForValidation(): void
    {
        $this->mergeAffiliationOrgInputForValidation();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $formValues = $this->baselineFormValues();
        $currentOrg = RegistryOrgAssignment::splitForRegistryForm(
            $formValues['section'] ?? null,
            $formValues['department'] ?? null,
            $formValues['location'] ?? null,
        );

        return [
            'effective_date' => ['required', 'date', 'after:today'],
            ...$this->affiliationOrgFieldRules(
                $formValues['department'] ?? null,
                $currentOrg['section'],
                $currentOrg['team'],
            ),
            'position' => ['nullable', 'string', 'max:255'],
            'job_description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'effective_date.required' => '反映日を入力してください。',
            'effective_date.after' => '反映日は明日以降の日付を指定してください。',
            ...$this->affiliationOrgFieldMessages(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'effective_date' => '反映日',
            'company' => '所属会社',
            'location' => '管轄',
            'department' => '部',
            'section' => '課',
            'team' => 'チーム',
            'position' => '役職',
            'job_description' => '業務内容',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);

        $validated = $this->resolveAffiliationOrgForStorage($validated);
        unset($validated['team']);

        return $validated;
    }

    /**
     * @return array{
     *     company: ?string,
     *     location: ?string,
     *     department: ?string,
     *     section: ?string,
     *     position: ?string,
     *     job_description: ?string
     * }
     */
    private function baselineFormValues(): array
    {
        $schedule = $this->route('schedule');
        $user = $this->route('user');

        if ($schedule instanceof ScheduledAffiliationChange) {
            $user = $schedule->user;
        }

        if (! $user instanceof User) {
            return ScheduledAffiliationChangeDiff::formValues(null, null);
        }

        $current = $user->currentAffiliation();
        $pending = $schedule instanceof ScheduledAffiliationChange ? $schedule : null;

        return ScheduledAffiliationChangeDiff::formValues($current, $pending);
    }
}
