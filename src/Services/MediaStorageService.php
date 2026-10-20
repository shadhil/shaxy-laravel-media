<?php

namespace Shaxy\Media\Services;

use Shaxy\Media\Data\MediaFile as MediaFileDTO;
use Shaxy\Media\Models\MediaFile as MediaFileModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaStorageService
{
    protected array $mimeMap = [
        'image' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/svg+xml'],
        'audio' => ['audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/wav', 'audio/aac', 'audio/webm'],
        'video' => ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'],
        'document' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
        ],
    ];

    protected array $directories = [
        'image' => 'images/{context}',
        'audio' => 'audio/{context}',
        'video' => 'videos/{context}',
        'document' => 'documents/{context}',
        'other' => 'files/{context}',
    ];

    protected ?Model $mediable = null;

    protected ?string $modelColumn = null;

    public function __construct(
        protected ImageProcessor $imageProcessor,
        protected string $disk = 'public',
    ) {
        $this->disk = config('media.disk', 'public');
        $this->directories = config('media.directories', $this->directories);
    }


    /**
     * Associate the next store() call with a model and column.
     *
     *   $media->for($organization, 'logo')->store($file, 'organizations/logos')
     */
    public function for(Model $model, string $column): static
    {
        $clone = clone $this;
        $clone->mediable = $model;
        $clone->modelColumn = $column;

        return $clone;
    }


    /**
     * Store any file — auto-detects type.
     */




    public function store(
        UploadedFile $file,
        string $context = 'general',
    ): MediaFileDTO {
        $mimeType = $file->getMimeType() ?? 'application/octet-stream';
        $mediaType = $this->classifyMime($mimeType);
        $directory = $this->resolveDirectory($mediaType, $context);

        return match ($mediaType) {
            'image' => $this->storeImage(
                $file,
                $directory,
                $mimeType,
                $context,
            ),
            default => $this->storeFile(
                $file,
                $directory,
                $mimeType,
                $mediaType,
                $context,
            ),
        };
    }

    /**
     * Force image processing regardless of column name hint.
     */
    public function storeImage(
        UploadedFile $file,
        string $directory,
        ?string $mimeType = null,
        ?string $context = null,
    ): MediaFileDTO {
        $mimeType = $mimeType ?? $file->getMimeType() ?? 'image/jpeg';
        $context = $context ?? $directory;
        $variants = $this->imageProcessor->process($file, $directory, $this->disk);

        $dto = new MediaFileDTO(
            disk: $this->disk,
            path: $variants['original'],
            filename: basename($variants['original']),
            directory: $directory,
            mimeType: $mimeType,
            type: 'image',
            size: $file->getSize(),
            originalFilename: $file->getClientOriginalName(),
            variants: $variants,
        );

        $this->index($dto, $file->getClientOriginalName());

        return $dto;
    }

    /**
     * Store a non-image file.
     */
    public function storeFile(
        UploadedFile $file,
        string $directory,
        ?string $mimeType = null,
        ?string $mediaType = null,
        ?string $context = null,
    ): MediaFileDTO {
        $mimeType = $mimeType ?? $file->getMimeType() ?? 'application/octet-stream';
        $mediaType = $mediaType ?? $this->classifyMime($mimeType);
        $context = $context ?? $directory;
        $extension = $file->getClientOriginalExtension();
        $filename = Str::ulid().'.'.$extension;
        $path = "{$directory}/{$filename}";

        Storage::disk($this->disk)->putFileAs($directory, $file, $filename);

        $dto = new MediaFileDTO(
            disk: $this->disk,
            path: $path,
            filename: $filename,
            directory: $directory,
            mimeType: $mimeType,
            type: $mediaType,
            size: $file->getSize(),
            originalFilename: $file->getClientOriginalName(),
            variants: null,
        );

        $this->index($dto, $file->getClientOriginalName());

        return $dto;
    }

    /**
     * Delete a stored file and soft-delete its index row.
     */
    public function delete(MediaFileDTO $dto): bool
    {
        if ($dto->type === 'image' && $dto->variants) {
            $this->imageProcessor->deleteVariants($dto->variants, $dto->disk);
        } else {
            Storage::disk($dto->disk)->delete($dto->path);
        }

        MediaFileModel::where('path', $dto->path)
            ->where('disk', $dto->disk)
            ->delete();

        return true;
    }

    public function deleteFromArray(array $data): bool
    {
        return $this->delete(MediaFileDTO::fromArray($data));
    }

    /**
     * Move all files to a different disk. Updates the index row too.
     */
    public function moveToDisk(MediaFileDTO $mediaFile, string $targetDisk): MediaFileDTO
    {
        if ($targetDisk === $mediaFile->disk) {
            return $mediaFile;
        }

        $allPaths = $mediaFile->variants
            ? array_values($mediaFile->variants)
            : [$mediaFile->path];

        foreach ($allPaths as $path) {
            $contents = Storage::disk($mediaFile->disk)->get($path);
            Storage::disk($targetDisk)->put($path, $contents);
        }

        if ($mediaFile->type === 'image' && $mediaFile->variants) {
            $this->imageProcessor->deleteVariants($mediaFile->variants, $mediaFile->disk);
        } else {
            Storage::disk($mediaFile->disk)->delete($mediaFile->path);
        }

        MediaFileModel::where('path', $mediaFile->path)
            ->where('disk', $mediaFile->disk)
            ->update(['disk' => $targetDisk]);

        return new MediaFileDTO(
            disk: $targetDisk,
            path: $mediaFile->path,
            filename: $mediaFile->filename,
            directory: $mediaFile->directory,
            mimeType: $mediaFile->mimeType,
            type: $mediaFile->type,
            size: $mediaFile->size,
            originalFilename: $mediaFile->originalFilename,
            variants: $mediaFile->variants,
        );
    }

    public function onDisk(string $disk): static
    {
        $clone = clone $this;
        $clone->disk = $disk;

        return $clone;
    }


    /**
     * Write a row to media_files for every stored file.
     * Called internally — never needs to be called from outside.
     */
    protected function index(MediaFileDTO $dto, ?string $originalFilename = null): MediaFileModel
    {
        $row = MediaFileModel::create([
            'disk' => $dto->disk,
            'path' => $dto->path,
            'directory' => $dto->directory,
            'filename' => $dto->filename,
            'mime_type' => $dto->mimeType,
            'type' => $dto->type,
            'size' => $dto->size,
            'context' => $this->resolveContext($dto->directory),
            'original_filename' => $originalFilename ?? $dto->originalFilename,
            'variants' => $dto->variants,
            'mediable_type' => $this->mediable ? get_class($this->mediable) : null,
            'mediable_id' => $this->mediable?->getKey(),
            'model_column' => $this->modelColumn,
            'uploaded_by' => Auth::id(),
        ]);

        $this->mediable = null;
        $this->modelColumn = null;

        return $row;
    }


    protected function classifyMime(string $mimeType): string
    {
        foreach ($this->mimeMap as $type => $mimes) {
            if (in_array($mimeType, $mimes, true)) {
                return $type;
            }
        }

        $top = explode('/', $mimeType)[0];

        return in_array($top, ['image', 'audio', 'video'], true) ? $top : 'other';
    }

    protected function resolveDirectory(string $mediaType, string $context): string
    {
        $template = $this->directories[$mediaType] ?? $this->directories['other'];

        return str_replace('{context}', $context, $template);
    }

    protected function resolveContext(string $directory): string
    {
        return preg_replace('#^(images|audio|video|documents|files)/#', '', $directory);
    }
}
