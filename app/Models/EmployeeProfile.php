<?php

namespace App\Models;

use App\Support\RequestUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeProfile extends Model
{
    protected $fillable = [
        'user_id',
        'english_name',
        'name_kana',
        'abbreviated_name',
        'joined_at',
        'nationality',
        'languages',
        'self_introduction',
        'photo_path',
        'import_locked',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'date',
            'import_locked' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function photoDisk(): string
    {
        return (string) config('filesystems.profile_photos_disk', 'public');
    }

    public function photoUrl(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        // 相対 URL にして、開いているホスト（Referer 元）と必ず一致させる
        return RequestUrl::route('profile.photo', ['user' => $this->user_id], absolute: false);
    }
}
