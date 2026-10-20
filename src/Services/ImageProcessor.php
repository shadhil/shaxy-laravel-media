<?php

namespace Shaxy\Media\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

class ImageProcessor
{



    protected array $variants;

    protected int $webpQuality;

    public function __construct(protected ImageManager $manager)
    {
        $this->variants = config('media.image_variants', [
            'thumbnail' => ['width' => 150, 'height' => 150],
            'standard' => ['width' => 800, 'height' => null],
            'large' => ['width' => 1920, 'height' => null],
        ]);

        $this->webpQuality = (int) config('media.webp_quality', 85);
    }

    /**
     * Process an uploaded image and store all variants + original.
     * Returns a path map: ['original' => '...', 'thumbnail' => '...', ...]
     *
     * @return array<string, string>
     */
    public function process(UploadedFile $file, string $directory, string $disk): array
    {
        $basename = (string) Str::ulid();
        $paths = [];

        $originalExt = $file->getClientOriginalExtension() ?: 'jpg';
        $paths['original'] = "{$directory}/{$basename}_original.{$originalExt}";

        Storage::disk($disk)->put(
            $paths['original'],
            file_get_contents($file->getRealPath())
        );

        $sourcePath = $file->getRealPath();

        if ($sourcePath === false || ! is_readable($sourcePath)) {
            throw new \RuntimeException('Uploaded image temporary file is not readable.');
        }


        foreach ($this->variants as $variant => $dimensions) {
            $width = $dimensions['width'];
            $height = $dimensions['height'] ?? null;

            $paths[$variant] = "{$directory}/{$basename}_{$variant}.webp";

            $image = $this->manager->decodePath($sourcePath);

            if ($height !== null) {
                $image->cover($width, $height);   // exact crop (thumbnail)
            } else {
                $image->scaleDown(width: $width); // proportional, never upscale
            }

            Storage::disk($disk)->put(
                $paths[$variant],
                (string) $image->encode(
                    new WebpEncoder(quality: $this->webpQuality)
                )
            );
        }

        return $paths;
    }

    public function deleteVariants(array $paths, string $disk): void
    {
        foreach ($paths as $path) {
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        }
    }

    /**
     * @param  array<string, array{width: int, height: int|null}>  $variants
     *
     * @callable  $processor->withVariants([
     *   'thumbnail' => ['width' => 200, 'height' => 200],
     *   'standard' => ['width' => 1000, 'height' => null],
     *  'large' => ['width' => 1920, 'height' => null]);
     */
    public function withVariants(array $variants): static
    {
        $this->variants = $variants;

        return $this;
    }

    public function withQuality(int $quality): static
    {
        $this->webpQuality = $quality;

        return $this;
    }
}
