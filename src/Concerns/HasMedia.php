<?php

namespace Shaxy\Media\Concerns;

use Shaxy\Media\Data\MediaFile;
use Shaxy\Media\Services\MediaStorageService;
use Illuminate\Http\UploadedFile;

/**
 * HasMedia trait
 *
 * Drop onto any Eloquent model that stores media in its own JSON columns.
 * Automatically passes model context to the service so every file is indexed.
 *
 * Model setup:
 *
 *   class Organization extends Model
 *   {
 *       use HasMedia;
 *
 *       protected $casts = [
 *           'logo'        => 'array',
 *           'cover_image' => 'array',
 *       ];
 *
 *       protected array $mediaColumns = [
 *           'logo'        => 'organizations/logos',
 *           'cover_image' => 'organizations/covers',
 *       ];
 *   }
 */
trait HasMedia
{












    public function storeMedia(string $column, UploadedFile $file): MediaFile
    {
        $service = $this->mediaService()->for($this, $column);
        $context = $this->mediaContext($column);
        $existing = $this->getMediaData($column);

        $mediaFile = $service->store($file, $context);

        $this->saveMediaReplacement($column, $mediaFile, $existing);

        return $mediaFile;
    }

    public function storeMediaImage(string $column, UploadedFile $file): MediaFile
    {
        $service = $this->mediaService()->for($this, $column);
        $context = $this->mediaContext($column);
        $directory = "images/{$context}";
        $existing = $this->getMediaData($column);

        $mediaFile = $service->storeImage($file, $directory);

        $this->saveMediaReplacement($column, $mediaFile, $existing);

        return $mediaFile;
    }


    public function mediaUrl(string $column, string $variant = 'original'): ?string
    {
        $data = $this->getMediaData($column);

        return $data ? MediaFile::fromArray($data)->url($variant) : null;
    }

    public function mediaUrls(string $column): ?array
    {
        $data = $this->getMediaData($column);

        return $data ? MediaFile::fromArray($data)->urls() : null;
    }

    public function mediaFile(string $column): ?MediaFile
    {
        $data = $this->getMediaData($column);

        return $data ? MediaFile::fromArray($data) : null;
    }


    public function deleteMedia(string $column): bool
    {
        $data = $this->getMediaData($column);
        if (! $data) {
            return false;
        }

        $deleted = app(MediaStorageService::class)->deleteFromArray($data);
        $this->setMediaData($column, null);
        $this->save();

        return $deleted;
    }

    public function deleteAllMedia(): void
    {
        foreach ($this->mediaColumns ?? [] as $column => $_) {
            $this->deleteMedia($column);
        }
    }


    protected function mediaService(): MediaStorageService
    {
        return app(MediaStorageService::class);
    }

    protected function mediaContext(string $column): string
    {
        return $this->mediaColumns[$column]
            ?? (class_basename($this).'/'.$column);
    }

    protected function getMediaData(string $column): ?array
    {
        $raw = $this->getAttribute($column);

        if (is_null($raw)) {
            return null;
        }

        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw)) {
            return json_decode($raw, true);
        }

        return null;
    }

    protected function setMediaData(string $column, ?array $data): void
    {
        $this->setAttribute($column, $data);
    }

    protected function saveMediaReplacement(
        string $column,
        MediaFile $mediaFile,
        ?array $existing,
    ): void {
        $connection = $this->getConnection();

        $connection->transaction(function () use (
            $connection,
            $column,
            $mediaFile,
            $existing,
        ) {
            $this->setMediaData($column, $mediaFile->toArray());

            if ($this->save() === false) {
                throw new \RuntimeException(
                    "Could not save replacement media for [{$column}]."
                );
            }

            if ($existing !== null) {
                $connection->afterCommit(function () use ($existing) {
                    try {
                        app(MediaStorageService::class)
                            ->deleteFromArray($existing);
                    } catch (\Throwable $exception) {
                        report($exception);
                    }
                });
            }
        });
    }
}
