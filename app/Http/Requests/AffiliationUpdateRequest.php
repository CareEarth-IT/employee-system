<?php

namespace App\Http\Requests;

use App\Models\AffiliationHistory;

class AffiliationUpdateRequest extends AffiliationStoreRequest
{
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
}
