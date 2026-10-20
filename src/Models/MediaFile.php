<?php

namespace Shaxy\Media\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaFile extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'variants' => 'array',
        'size' => 'integer',
    ];


    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });
    }


    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(config('media.user_model') ?: config('auth.providers.users.model'), 'uploaded_by');
    }


    public function url(string $variant = 'original'): string
    {
        if ($this->type === 'image' && $this->variants && isset($this->variants[$variant])) {
            return Storage::disk($this->disk)->url($this->variants[$variant]);
        }

        return Storage::disk($this->disk)->url($this->path);
    }

    public function urls(): array
    {
        if ($this->type !== 'image' || ! $this->variants) {
            return ['original' => $this->url()];
        }

        return collect($this->variants)
            ->mapWithKeys(fn ($path, $variant) => [
                $variant => Storage::disk($this->disk)->url($path),
            ])
            ->all();
    }

    public function thumbnailUrl(): string
    {
        return $this->url('thumbnail');
    }


    public function humanSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->size;

        for ($i = 0; $size >= 1024 && $i < 3; $i++) {
            $size /= 1024;
        }

        return round($size, 2).' '.$units[$i];
    }

    public function isImage(): bool
    {
        return $this->type === 'image';
    }

    public function extension(): string
    {
        return pathinfo($this->filename, PATHINFO_EXTENSION);
    }


    public function scopeImages($query)
    {
        return $query->where('type', 'image');
    }

    public function scopeDocuments($query)
    {
        return $query->where('type', 'document');
    }

    public function scopeAudio($query)
    {
        return $query->where('type', 'audio');
    }

    public function scopeOfContext($query, string $context)
    {
        return $query->where('context', $context);
    }

    public function scopeOrphaned($query)
    {
        return $query->whereNull('mediable_type');
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('original_filename', 'like', "%{$term}%")
                ->orWhere('context', 'like', "%{$term}%")
                ->orWhere('mime_type', 'like', "%{$term}%");
        });
    }
}
