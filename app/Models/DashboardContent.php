<?php

namespace App\Models;

use App\Services\DashboardContentStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardContent extends Model
{
    protected $fillable = [
        'department',
        'content_html',
        'content_path',
        'page_url',
        'is_visible',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
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
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForDepartment(Builder $query, string $department): Builder
    {
        return $query->where('department', $department);
    }

    public function resolvedText(): string
    {
        $raw = $this->content_path
            ? $this->resolvedHtml()
            : ($this->content_html ?? '');

        return trim(strip_tags(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    public static function persistText(
        string $department,
        string $text,
        int $userId,
        ?int $id = null,
    ): self {
        $cleaned = trim(preg_replace("/\r\n?/", "\n", strip_tags($text)));

        if ($id) {
            $content = self::query()->findOrFail($id);
        } else {
            $content = new self(['department' => $department]);
        }

        if ($content->content_path) {
            DashboardContentStorage::delete($content->content_path);
        }

        $content->fill([
            'department' => $department,
            'content_html' => $cleaned,
            'content_path' => null,
            'page_url' => null,
            'is_visible' => true,
            'updated_by' => $userId,
        ]);
        $content->save();

        return $content->fresh(['updatedByUser']);
    }

    public function resolvedHtml(): string
    {
        $fromStorage = DashboardContentStorage::read($this->content_path);

        if (is_string($fromStorage) && $fromStorage !== '') {
            return $fromStorage;
        }

        return $this->content_html ?? '';
    }

    public static function persistHtml(
        string $department,
        string $html,
        int $userId,
        ?string $pageUrl = null,
        ?int $id = null,
        ?bool $isVisible = null,
    ): self {
        $cleaned = \App\Support\DashboardContentSanitizer::clean($html);

        if ($id) {
            $content = self::query()->findOrFail($id);
        } else {
            $content = new self(['department' => $department]);
        }

        $content->fill([
            'department' => $department,
            'content_html' => $cleaned,
            'page_url' => $pageUrl,
            'is_visible' => $isVisible ?? ($content->exists ? $content->is_visible : true),
            'updated_by' => $userId,
        ]);
        $content->save();

        $path = DashboardContentStorage::storeHtml($department, $cleaned, $content->id);
        $content->content_path = $path;
        $content->save();

        return $content->fresh(['updatedByUser']);
    }
}
