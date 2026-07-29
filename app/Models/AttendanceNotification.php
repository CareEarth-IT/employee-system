<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceNotification extends Model
{
    public const TYPE_ABSENCE = 'absence';

    public const TYPE_LATE = 'late';

    public const TYPE_EARLY_LEAVE = 'early_leave';

    public const TYPE_PAID_LEAVE = 'paid_leave';

    /** @var array<string, string> */
    public const TYPE_LABELS = [
        self::TYPE_ABSENCE => '欠勤',
        self::TYPE_LATE => '遅刻',
        self::TYPE_EARLY_LEAVE => '早退',
        self::TYPE_PAID_LEAVE => '有休',
    ];

    protected $fillable = [
        'user_id',
        'notification_type',
        'target_date',
        'end_date',
        'scheduled_time',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'target_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->notification_type] ?? $this->notification_type;
    }

    public function requiresScheduledTime(): bool
    {
        return in_array($this->notification_type, [self::TYPE_LATE, self::TYPE_EARLY_LEAVE], true);
    }

    public function scheduledTimeLabel(): string
    {
        return match ($this->notification_type) {
            self::TYPE_LATE => '到着予定時刻',
            self::TYPE_EARLY_LEAVE => '退勤予定時刻',
            default => '予定時刻',
        };
    }

    public function scheduledTimeDisplay(): ?string
    {
        if (! $this->scheduled_time) {
            return null;
        }

        return substr((string) $this->scheduled_time, 0, 5);
    }

    public function dateRangeDisplay(): string
    {
        $start = $this->target_date->format('Y/m/d');

        if ($this->end_date && ! $this->end_date->equalTo($this->target_date)) {
            return "{$start} 〜 {$this->end_date->format('Y/m/d')}";
        }

        return $start;
    }
}
