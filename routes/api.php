<?php

use App\Http\Controllers\Api\V1\FormController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:web')->group(function () {
    Route::post('forms', [FormController::class, 'store'])->name('forms.store');
});
