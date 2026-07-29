<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class DevelopmentRequest extends Model
{
    public const PROGRESS_OPTIONS = [
        '相談前',
        '相談済（未完）',
        '開発待ち',
        '開発中',
        '動作確認中（未完）',
        '完了',
    ];

    public const DEV_ASSIGNEE_OPTIONS = [
        '未',
        'フン',
        'カデアー',
        'パース',
        '南谷',
        '林',
        '中元',
        '増井',
    ];

    /** @var array<string, string> 保存値 => 一覧・詳細の表示ラベル */
    public const CONTENT_TYPE_LABELS = [
        '派遣の開発システム' => '派遣開発',
        '派遣以外の開発システム' => '派遣以外',
        'ソフトやGoogleサービスについて' => 'ソフト/Google',
        '新たな依頼' => '新規',
        '修正' => '修正',
        'PC, Wifi, スマホ' => 'PC/Wifi/スマホ',
        'その他' => 'その他',
        'Airtable' => 'Airtable',
    ];

    /** @var list<string> 新規依頼フォームの選択肢（保存値） */
    public const CONTENT_TYPE_OPTIONS = [
        '派遣の開発システム',
        '派遣以外の開発システム',
        'ソフトやGoogleサービスについて',
        '新たな依頼',
        'PC, Wifi, スマホ',
        'その他',
        'Airtable',
    ];

    /** @var array<string, string> フォーム表示用ラベル */
    public const CONTENT_TYPE_FORM_LABELS = [
        '派遣の開発システム' => '派遣の開発システム　⇒　カデアー',
        '派遣以外の開発システム' => '派遣以外の開発システム　⇒　増井',
        'ソフトやGoogleサービスについて' => 'ソフトやGoogleサービスについて　⇒　増井',
        '新たな依頼' => '新たな依頼（組換系・中央のスケジュール予約セット）　⇒　中元',
        'PC, Wifi, スマホ' => 'PC, Wifi, スマホ , ....　⇒　林、南谷',
        'その他' => 'その他　⇒　中元',
        'Airtable' => 'Airtable について問い合わせ　⇒　カデアー',
    ];

    protected $fillable = [
        'request_number',
        'user_id',
        'requester_name',
        'requester_department',
        'requester_number',
        'requester_email',
        'request_date',
        'content_type',
        'sub_type',
        'title',
        'purpose',
        'detail',
        'progress',
        'remarks',
        'estimated_hours',
        'actual_hours',
        'development_target_date',
        'development_assignee',
        'manager',
    ];

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
            'development_target_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getRouteKeyName(): string
    {
        return 'request_number';
    }

    public static function nextRequestNumber(): int
    {
        $start = (int) config('development_requests.request_number_start', 10001);
        $max = (int) static::query()->max('request_number');

        return max($max + 1, $start);
    }

    public static function resolveManager(string $contentType): string
    {
        $type = trim($contentType);

        if (str_contains($type, '派遣の開発')) {
            return 'カデアー';
        }

        if (str_contains($type, 'PC') || str_contains($type, 'Wifi') || str_contains($type, 'スマホ')) {
            return '林、南谷';
        }

        if ($type === 'その他' || str_contains($type, '新')) {
            return '中元';
        }

        if (str_contains($type, 'Airtable')) {
            return 'カデアー';
        }

        return '増井';
    }

    public static function contentTypeFromLabel(string $label): string
    {
        $normalized = trim($label);
        $flipped = array_flip(self::CONTENT_TYPE_LABELS);

        if (isset($flipped[$normalized])) {
            return $flipped[$normalized];
        }

        if (isset(self::CONTENT_TYPE_LABELS[$normalized])) {
            return $normalized;
        }

        throw new \InvalidArgumentException('Type の値が正しくありません。');
    }

    public function contentTypeLabel(): string
    {
        return self::CONTENT_TYPE_LABELS[$this->content_type]
            ?? $this->displayContentTypeFallback($this->content_type);
    }

    public function displayContentTypeFallback(string $type): string
    {
        $type = trim($type);

        if (str_contains($type, '派遣の開発')) {
            return '派遣開発';
        }
        if (str_contains($type, '派遣以外')) {
            return '派遣以外';
        }
        if (str_contains($type, 'ソフト') || str_contains($type, 'Googleサービス')) {
            return 'ソフト/Google';
        }
        if (str_contains($type, '新')) {
            return '新規';
        }
        if (str_contains($type, '修正')) {
            return '修正';
        }
        if (str_contains($type, 'PC') || str_contains($type, 'Wifi') || str_contains($type, 'スマホ')) {
            return 'PC/Wifi/スマホ';
        }
        if (str_contains($type, 'その他')) {
            return 'その他';
        }
        if (str_contains($type, 'Airtable')) {
            return 'Airtable';
        }

        return $type !== '' ? $type : '—';
    }

    public static function normalizeProgress(?string $value): string
    {
        $progress = trim((string) $value);

        return match ($progress) {
            '相談済' => '相談済（未完）',
            '動作確認' => '動作確認中（未完）',
            '' => '相談前',
            default => $progress,
        };
    }

    public function titleShort(int $maxLen = 30): string
    {
        $text = trim((string) $this->title);
        if (mb_strlen($text) <= $maxLen) {
            return $text;
        }

        return mb_substr($text, 0, $maxLen).'...';
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithinListMonths(Builder $query): Builder
    {
        $months = max(1, (int) config('development_requests.list_months', 3));
        $cutoff = Carbon::now()->subMonths($months)->startOfDay();

        return $query->where(function (Builder $inner) use ($cutoff) {
            $inner->where('request_date', '>=', $cutoff->toDateString())
                ->orWhere('created_at', '>=', $cutoff);
        });
    }
}
