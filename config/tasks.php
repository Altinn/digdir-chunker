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

    'default_chunk_size' => env('TASKS_DEFAULT_CHUNK_SIZE', 1024),

    /*
    |--------------------------------------------------------------------------
    | Default chunk overlap
    |--------------------------------------------------------------------------
    |
    | The max number of characters that can overlap between chunks.
    |
    */

    'default_chunk_overlap' => env('TASKS_DEFAULT_CHUNK_OVERLAP', 256),
    

    /*
    |--------------------------------------------------------------------------
    | Default task deletion delay
    |--------------------------------------------------------------------------
    |
    | Tasks are automatically deleted after this delay in minutes, unless another delay was specified by the user.
    |
    */

    'default_deletion_delay_minutes' => env('TASKS_DEFAULT_DELETION_DELAY_MINUTES', 24*60*30), // 30 days

    /*
    |--------------------------------------------------------------------------
    | Generate chunk derivatives
    |--------------------------------------------------------------------------
    |
    | Whether to automatically generate chunk derivatives (summaries, etc.) 
    | when processing documents.
    |
    */

    'generate_chunk_derivatives' => env('TASKS_GENERATE_CHUNK_DERIVATIVES', false),

    /*
    |--------------------------------------------------------------------------
    | Generate embeddings
    |--------------------------------------------------------------------------
    |
    | Whether to automatically generate embeddings for chunks when processing documents.
    |
    */

    'generate_embeddings' => env('TASKS_GENERATE_EMBEDDINGS', false),

];
