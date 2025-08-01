<?php

use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::prefix('task')->group(function () {

        Route::post('parse', [TaskController::class, 'create']);
        Route::get('{task}', [TaskController::class, 'show'])->name('task.show');
        Route::post('{task}/cancel', [TaskController::class, 'cancel'])->name('task.cancel');
        Route::delete('{task}', [TaskController::class, 'delete'])->name('task.delete');
    });
});