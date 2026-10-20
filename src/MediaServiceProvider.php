<?php

namespace Shaxy\Media;

use Illuminate\Support\ServiceProvider;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Shaxy\Media\Services\ImageProcessor;
use Shaxy\Media\Services\MediaStorageService;

class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/media.php', 'media');

        $this->app->bind(ImageProcessor::class, function () {
            return new ImageProcessor(new ImageManager(new Driver()));
        });

        $this->app->bind(MediaStorageService::class, function ($app) {
            return new MediaStorageService($app->make(ImageProcessor::class));
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/media.php' => config_path('media.php'),
            ], 'shaxy-media-config');

            // Preserve the original filename for migration tracking.
            // Migrations are not loaded automatically.
            $this->publishes([
                __DIR__.'/../database/migrations/2026_05_18_112240_create_media_files_table.php'
                    => database_path('migrations/2026_05_18_112240_create_media_files_table.php'),
            ], 'shaxy-media-migrations');
        }
    }
}
