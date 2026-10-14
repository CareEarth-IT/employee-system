<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialInsuranceThresholdNotification extends Model
{
    protected $fillable = [
        'company',
        'threshold',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'threshold' => 'integer',
            'notified_at' => 'datetime',
        ];
    }
}
