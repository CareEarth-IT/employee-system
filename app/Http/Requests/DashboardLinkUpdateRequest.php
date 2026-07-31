<?php

namespace App\Http\Requests;

use App\Models\DashboardLink;
use App\Support\DashboardTab;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardLinkUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tab = \App\Support\DashboardTab::findByKey((string) $this->input('tab', 'common'));

        if ($tab === null) {
            return false;
        }

        return $this->user()?->canManageDashboardContents() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tab' => ['required', 'string', Rule::in(array_map(
                static fn (array $tab) => $tab['key'],
                \App\Support\DashboardTab::all(),
            ))],
            'links' => ['nullable', 'array'],
            'links.*.label' => ['nullable', 'string', 'max:255'],
            'links.*.url' => ['nullable', 'string', 'max:2048'],
            'links.*.kind' => ['nullable', Rule::in([
                DashboardLink::KIND_LINK,
                DashboardLink::KIND_FORM_POST,
                DashboardLink::KIND_MODAL,
            ])],
            'links.*.action_route' => ['nullable', 'string', 'max:100'],
            'links.*.modal_target' => ['nullable', 'string', 'max:100'],
            'links.*.visibility_rule' => ['nullable', 'string', 'max:50'],
            'links.*.category_key' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9_-]*$/'],
            'links.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'links.*.is_visible' => ['nullable', 'boolean'],
            'categories' => ['nullable', 'array'],
            'categories.*.label' => ['nullable', 'string', 'max:100'],
            'categories.*.category_key' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9_-]+$/'],
            'categories.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! DashboardTab::supportsLinkCategories((string) $this->input('tab', 'common'))) {
            $this->merge(['categories' => []]);
        }
    }
}
