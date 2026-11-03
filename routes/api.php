<?php

use App\Http\Controllers\Api\V1\FormController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:web')->group(function () {
    Route::get('forms/mine', [FormController::class, 'mine'])->name('forms.mine');
    Route::post('forms', [FormController::class, 'store'])->name('forms.store');
});
