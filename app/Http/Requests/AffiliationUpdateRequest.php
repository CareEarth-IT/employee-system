<?php

namespace App\Http\Requests;

use App\Models\AffiliationHistory;

class AffiliationUpdateRequest extends AffiliationStoreRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        if (! $this->shouldResolveAffiliationOrgForStorage()) {
            $rules['department'] = ['nullable', 'string', 'max:255'];
            $rules['section'] = ['nullable', 'string', 'max:255'];
            unset($rules['team']);
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $affiliation = $this->route('affiliation');

        if (
            $affiliation instanceof AffiliationHistory
            && $affiliation->isCurrent()
            && $this->user()
            && ! $this->user()->canEditCurrentAffiliationOrg()
        ) {
            $this->merge([
                'is_current' => true,
                'start_date' => $affiliation->start_date?->format('Y-m-d'),
                'end_date' => $affiliation->end_date?->format('Y-m-d'),
                'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
                'department' => $affiliation->department,
                'section' => $affiliation->section,
            ]);

            return;
        }

        parent::prepareForValidation();
    }

    protected function shouldResolveAffiliationOrgForStorage(): bool
    {
        $affiliation = $this->route('affiliation');

        if (
            $affiliation instanceof AffiliationHistory
            && $affiliation->isCurrent()
            && $this->user()
            && ! $this->user()->canEditCurrentAffiliationOrg()
        ) {
            return false;
        }

        return true;
    }
}
