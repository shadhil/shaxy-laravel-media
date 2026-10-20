<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Storage Disk
    |--------------------------------------------------------------------------
    | Change this one value to switch to S3, Cloudflare R2, etc.
    | Local: 'public' | AWS S3: 's3' | Cloudflare R2: 'r2'
    */
    'disk' => env('MEDIA_DISK', 'public'),

    // Null uses auth.providers.users.model.
    'user_model' => null,

    /*
    |--------------------------------------------------------------------------
    | Image Variants
    |--------------------------------------------------------------------------
    | height: null = maintain aspect ratio | integer = exact crop (cover)
    */
    'image_variants' => [
        'thumbnail' => ['width' => 150,  'height' => 150],
        'standard' => ['width' => 800,  'height' => null],
        'large' => ['width' => 1920, 'height' => null],
    ],

    'webp_quality' => env('MEDIA_WEBP_QUALITY', 85),

    /*
    |--------------------------------------------------------------------------
    | Max File Sizes (in kilobytes)
    |--------------------------------------------------------------------------
    */
    'max_sizes' => [
        'image' => 10240,
        'document' => 51200,
        'audio' => 102400,
        'video' => 512000,
        'other' => 20480,
    ],

    /*
    |--------------------------------------------------------------------------
    | Directory Structure  ({context} replaced at runtime)
    |--------------------------------------------------------------------------
    */
    'directories' => [
        'image' => 'images/{context}',
        'audio' => 'audio/{context}',
        'video' => 'videos/{context}',
        'document' => 'documents/{context}',
        'other' => 'files/{context}',
    ],

];








