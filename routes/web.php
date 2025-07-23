<?php

use Illuminate\Support\Facades\Route;

Route::get("/files/{uuid}/images/{filename}", [App\Http\Controllers\FileController::class, 'getImage'])
    ->name('file.image');