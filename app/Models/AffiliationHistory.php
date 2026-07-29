<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliationHistory extends Model
{
    public const STATUS_ENROLLED = '在籍中';

    public const STATUS_MOVED = '他へ移動済';

    public const STATUS_RESIGNED = '退職';

    /** @var list<string> */
    public const ENROLLMENT_STATUSES = [
        self::STATUS_ENROLLED,
        self::STATUS_MOVED,
        self::STATUS_RESIGNED,
    ];

    protected $fillable = [
        'user_id',
        'start_date',
        'end_date',
        'enrollment_status',
        'company',
        'location',
        'department',
        'section',
        'position',
        'job_description',
        'import_locked',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'import_locked' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isEnrolled(): bool
    {
        return $this->enrollment_status === self::STATUS_ENROLLED;
    }

    public function isCurrent(): bool
    {
        if (! $this->isEnrolled()) {
            return false;
        }

        return $this->end_date === null || $this->end_date->toDateString() >= now()->toDateString();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCurrentlyActive($query)
    {
        return $query
            ->where('enrollment_status', self::STATUS_ENROLLED)
            ->where(function ($active) {
                $active->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->toDateString());
            });
    }

    public function closeAsMoved(?string $endDate = null): void
    {
        $this->update([
            'enrollment_status' => self::STATUS_MOVED,
            'end_date' => $endDate ?? now()->toDateString(),
        ]);
    }
}
