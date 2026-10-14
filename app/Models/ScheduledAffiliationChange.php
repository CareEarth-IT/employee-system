<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledAffiliationChange extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'created_by',
        'effective_date',
        'company',
        'location',
        'department',
        'section',
        'position',
        'job_description',
        'status',
        'applied_affiliation_id',
        'applied_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'applied_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function appliedAffiliation(): BelongsTo
    {
        return $this->belongsTo(AffiliationHistory::class, 'applied_affiliation_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isDue(?string $onDate = null): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        $onDate ??= now()->toDateString();

        return $this->effective_date !== null
            && $this->effective_date->toDateString() <= $onDate;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeDue($query, ?string $onDate = null)
    {
        $onDate ??= now()->toDateString();

        return $query
            ->pending()
            ->whereDate('effective_date', '<=', $onDate);
    }
}
