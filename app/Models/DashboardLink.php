<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardLink extends Model
{
    public const KIND_LINK = 'link';

    public const KIND_FORM_POST = 'form_post';

    public const KIND_MODAL = 'modal';

    protected $fillable = [
        'tab_key',
        'label',
        'url',
        'kind',
        'action_route',
        'modal_target',
        'visibility_rule',
        'sort_order',
        'is_visible',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_visible' => 'boolean',
        ];
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForTab(Builder $query, string $tabKey): Builder
    {
        return $query->where('tab_key', $tabKey);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function isLinkKind(): bool
    {
        return $this->kind === self::KIND_LINK;
    }

    public function isFormPostKind(): bool
    {
        return $this->kind === self::KIND_FORM_POST;
    }

    public function isModalKind(): bool
    {
        return $this->kind === self::KIND_MODAL;
    }

    public function resolvedActionUrl(): ?string
    {
        if (! $this->action_route) {
            return null;
        }

        return route($this->action_route);
    }
}
