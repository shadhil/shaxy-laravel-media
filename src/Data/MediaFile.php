<?php

namespace Shaxy\Media\Data;

use Illuminate\Support\Facades\Storage;

/**
 * Value object returned by MediaStorageService.
 * Not to be confused with Shaxy\Media\Models\MediaFile (the Eloquent model / index row).
 */
class MediaFile
{
    public function __construct(
        public readonly string $disk,
        public readonly string $path,
        public readonly string $filename,
        public readonly string $directory,
        public readonly string $mimeType,
        public readonly string $type,
        public readonly int $size,
        public readonly ?string $originalFilename = null,
        public readonly ?array $variants = null,
    ) {}

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

    public function toArray(): array
    {
        return [
            'disk' => $this->disk,
            'path' => $this->path,
            'filename' => $this->filename,
            'directory' => $this->directory,
            'mime_type' => $this->mimeType,
            'type' => $this->type,
            'size' => $this->size,
            'original_filename' => $this->originalFilename,
            'variants' => $this->variants,
            'urls' => $this->urls(),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            disk: $data['disk'],
            path: $data['path'],
            filename: $data['filename'],
            directory: $data['directory'],
            mimeType: $data['mime_type'],
            type: $data['type'],
            size: $data['size'],
            originalFilename: $data['original_filename'] ?? null,
            variants: $data['variants'] ?? null,
        );
    }
}
