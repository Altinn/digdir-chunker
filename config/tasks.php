<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default document conversion backend
    |--------------------------------------------------------------------------
    |
    | The backend used for converting documents.
    | Currently, only 'marker' is supported.
    |
    */

    'default_conversion_backend' => env('TASKS_DEFAULT_CONVERSION_BACKEND', 'marker'),

    /*
    |--------------------------------------------------------------------------
    | Default chunking method
    |--------------------------------------------------------------------------
    |
    | The chunking method used for splitting documents into smaller parts.
    | Currently, only 'semantic' is supported.
    |
    */

    'default_chunking_method' => env('TASKS_DEFAULT_CHUNKING_METHOD', 'semantic'),

    /*
    |--------------------------------------------------------------------------
    | Default chunking target length
    |--------------------------------------------------------------------------
    |
    | The target length of each chunk in characters.
    |
    */

    'default_chunking_target_length' => env('TASKS_DEFAULT_CHUNKING_TARGET_LENGTH', 512),


];
