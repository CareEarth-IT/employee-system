<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyAffiliationRecord extends Model
{
    protected $fillable = [
        'year_month',
        'user_id',
        'name',
        'last_name_sort',
        'employee_id',
        'location',
        'department',
        'section',
        'captured_by_user_id',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by_user_id');
    }

    public static function isValidYearMonth(string $yearMonth): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}$/', $yearMonth);
    }

    public static function formatYearMonthLabel(string $yearMonth): string
    {
        [$year, $month] = explode('-', $yearMonth);

        return "{$year}年".(int) $month.'月';
    }
}
