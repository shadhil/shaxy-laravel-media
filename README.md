# Shaxy Laravel Media

Reusable extraction of your supplied media implementation for PHP 8.4+, Laravel 13 and Intervention Image 4.1+. Uses GD with WebP support. Package name: `shaxy/laravel-media`.

## Install in the existing project

1. Extract this folder to `packages/shaxy/laravel-media` in the API project. The package's composer.json must be directly inside that directory.
2. Add this entry to the root composer.json `repositories` array, preserving existing entries:

```json
{
  "type": "path",
  "url": "packages/shaxy/laravel-media",
  "options": {
    "versions": { "shaxy/laravel-media": "dev-main" }
  }
}
```

3. Run from the API project root:

```bash
composer require shaxy/laravel-media:dev-main
```

4. Replace application imports using this mapping:

| Before                                   | After                                      |
| ---------------------------------------- | ------------------------------------------ |
| `App\Traits\HasMedia`                    | `Shaxy\Media\Concerns\HasMedia`            |
| `App\DTOs\MediaFile`                     | `Shaxy\Media\Data\MediaFile`               |
| `App\Models\MediaFile`                   | `Shaxy\Media\Models\MediaFile`             |
| `App\Services\Media\ImageProcessor`      | `Shaxy\Media\Services\ImageProcessor`      |
| `App\Services\Media\MediaStorageService` | `Shaxy\Media\Services\MediaStorageService` |

5. Remove `App\Providers\MediaServiceProvider::class` from `bootstrap/providers.php`. The package provider is discovered automatically. Update any other old references before removing the old six classes. Keep application controllers and models such as Post.
6. Keep the existing `config/media.php` and original media table migration. **Do not publish a second create-table migration or rebuild the existing table.** No data migration is required for ordinary existing media JSON/index rows; their keys and schema are unchanged. Audit any other application records that store the old MediaFile model class name before deleting that class.
7. Refresh configuration/autoloading:

```bash
composer dump-autoload
php artisan config:cache
```

Restart long-running queue/Octane workers if the application uses them. Commit both the package directory and root Composer files while using a local path repository. Deploy the package directory before running `composer install` on the server.

## Install in a new project

Use the same local Composer setup, then:

```bash
php artisan vendor:publish --tag=shaxy-media-config
php artisan vendor:publish --tag=shaxy-media-migrations
php artisan migrate
php artisan storage:link
```

The supplied migration is preserved exactly: it requires a `users` table with numeric IDs, and normally uses numeric morph IDs. Ensure users is created before media_files. If a new project uses UUIDs/ULIDs or another users table, adapt the published migration BEFORE running it. A configurable uploader model does not change the migration's foreign key.

## Use on a model

```php
use Shaxy\Media\Concerns\HasMedia;

class Post extends Model
{
    use HasMedia;

    protected $casts = ['featured_image' => 'array'];

    protected array $mediaColumns = [
        'featured_image' => 'posts/featured-images',
    ];
}
```

Merge these declarations with the model's existing casts and properties. Save the model before attaching media so it has an ID.

```php
$post->storeMediaImage('featured_image', $request->file('featured_image'));
$url = $post->mediaUrl('featured_image', 'thumbnail');
$urls = $post->mediaUrls('featured_image');
```

For other files:

```php
$post->storeMedia('attachment', $request->file('attachment'));
```

Add the corresponding JSON column/cast and mediaColumns mapping in your application.

For standalone files:

```php
use Shaxy\Media\Services\MediaStorageService;

$media = app(MediaStorageService::class)->store($file, 'editor');
```

## Configuration

- `media.disk`: Laravel filesystem disk; configure credentials and URLs in the host application.
- `media.image_variants`: named width/height arrays; integer height crops, null height scales proportionally.
- `media.webp_quality`: default 85.
- `media.user_model`: optional uploader model override; null falls back to `auth.providers.users.model`.
- `media.directories`: used by the general `store()` method. The original trait's `storeMediaImage()` intentionally retains its `images/{context}` path.
- `media.max_sizes`: retained configuration values, **not automatically enforced by this implementation**. Validate uploads in your request/controller before calling the package.

For example, validate public image uploads with an explicit allowlist:

```php
$request->validate([
    'featured_image' => 'required|image|mimes:jpg,jpeg,png,webp|max:10240',
]);
```

The MIME classifier is not an upload allowlist. SVG appears in the original classifier but is not supported by the GD variant-processing flow. Do not route SVG/HEIC uploads through this image processor. Apply authorization and file-type validation in the application.

## Preserved fixes and extraction changes

- Keeps `decodePath($sourcePath)` for Intervention v4.
- Keeps old media until replacement metadata commits; old cleanup uses afterCommit.
- Indexing happens once in storeImage/storeFile.
- Uses named dimensions from config, loaded by the ImageProcessor constructor.
- Moves namespaces to Shaxy\Media; keeps DTO class name MediaFile to reduce migration churn.
- Removes obsolete commented-out implementation blocks.
- Fresh service bindings avoid shared mutable processor overrides.
- Does not globally replace the host application's ImageManager binding.
- Makes uploader relationship configurable and loads directory configuration for store().
- Makes moveToDisk() a no-op for the current disk, avoiding deletion of the source itself.

## Remaining inherited limitations

This is a reusable extraction, not a full redesign of filesystem/database consistency:

- Storage write/delete return values are not comprehensively checked. Consider enabling `throw => true` for the selected filesystem disk, and still test failure behavior.
- Failed processing/indexing/model saving can leave new files or index rows behind. Index creation occurs before the trait's metadata-save transaction unless the caller already opened one on the same connection.
- `deleteMedia()` deletes files before saving the cleared attribute. Disk moves delete source files before updating the index and do not update the owning model's JSON automatically. Treat these as separate hardening work before relying on rollback behavior.
- Uploaded original filenames supply extensions; callers must validate file content/type before storing publicly.
- Concurrent replacements are not serialized.
- `get_class()` for mediable_type is preserved; applications using enforced morph maps need an intentional migration to getMorphClass(), including existing rows.
- Stored JSON includes URLs; helpers calculate current URLs, but stored `urls` values can become stale after a domain/disk URL change.
- Private storage needs appropriate signed-URL handling; the current URL helpers use Storage::url().

## Verify in your application

PHP/Composer were not installed in the packaging environment, so runtime verification must be performed in the host project:

```bash
composer validate packages/shaxy/laravel-media/composer.json
php -l packages/shaxy/laravel-media/src/MediaServiceProvider.php
php artisan test
```

Also lint all other package PHP files and check:

1. Upload a JPG and verify original, thumbnail, standard and large files.
2. Check exactly one active media_files row with the correct owner/column.
3. Replace the image and confirm new URLs work and old files are deleted after commit.
4. Trigger a replacement failure and confirm old files remain.
5. Upload through store() and direct storeImage() independently; verify one row each.
6. Verify no database migration attempts to recreate media_files in the existing application.
7. Test uploader() and existing saved media URLs.

## Reuse from a private Git repository

Move this package directory's contents into a separate repository, keeping composer.json at the repository root. After testing, tag a release such as v1.0.0. Add the actual repository URL as a Composer `vcs` repository in consuming applications and install `shaxy/laravel-media:^1.0`. Configure repository authentication outside committed files. Remove the path repository entry when switching to Git installation.

## Server-specific Livewire preview fix

The Nginx preview routing fix is an application/server deployment concern, not part of this package. Keep that custom Nginx include when moving the admin website and update its Livewire route prefix if it changes.
