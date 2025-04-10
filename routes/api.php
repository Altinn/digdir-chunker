<?php

use App\Http\Controllers\DocumentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/chunk', [DocumentController::class, 'chunk']);

Route::get('/search', [DocumentController::class, 'search']);