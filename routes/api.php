<?php

use App\Http\Controllers\Api\V1\FormController;
use App\Http\Controllers\Api\V1\PublicFormController;
use App\Http\Controllers\Api\V1\PublicSubmissionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:web')->group(function () {
    Route::get('forms/mine', [FormController::class, 'mine'])->name('forms.mine');
    Route::post('forms', [FormController::class, 'store'])->name('forms.store');
});

Route::prefix('public/forms/{form:public_id}')->whereUuid('form')->name('public.forms.')->group(function () {
    Route::get('/', [PublicFormController::class, 'show'])->name('show');
    Route::post('submissions', [PublicSubmissionController::class, 'store'])
        ->middleware('throttle:public-submissions')->name('submissions.store');
});
