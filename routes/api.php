<?php

use App\Http\Controllers\Api\V1\FormController;
use App\Http\Controllers\Api\V1\FormExportController;
use App\Http\Controllers\Api\V1\PublicFormController;
use App\Http\Controllers\Api\V1\PublicSubmissionController;
use App\Http\Controllers\Api\V1\SubmissionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:web')->group(function () {
    Route::get('forms/mine', [FormController::class, 'mine'])->name('forms.mine');
    Route::get('forms', [FormController::class, 'index'])->name('forms.index');
    Route::get('forms/{form}', [FormController::class, 'show'])->whereNumber('form')->name('forms.show');
    Route::get('forms/{form}/submissions', [SubmissionController::class, 'index'])->whereNumber('form')->name('forms.submissions.index');
    Route::get('forms/{form}/exports/csv', [FormExportController::class, 'csv'])->whereNumber('form')->name('forms.exports.csv');
    Route::get('forms/{form}/exports/pdf', [FormExportController::class, 'pdf'])->whereNumber('form')->name('forms.exports.pdf');
    Route::post('forms', [FormController::class, 'store'])->name('forms.store');
});

Route::prefix('public/forms/{form:public_id}')->whereUuid('form')->name('public.forms.')->group(function () {
    Route::get('/', [PublicFormController::class, 'show'])->name('show');
    Route::post('submissions', [PublicSubmissionController::class, 'store'])
        ->middleware('throttle:public-submissions')->name('submissions.store');
});
