<?php

use App\Models\Form;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn (Request $request) => redirect()->route(
    $request->user() ? 'dashboard' : 'login',
))->name('home');

Route::middleware(['auth'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::inertia('forms/create', 'forms/Create')->name('forms.create');
    Route::inertia('forms', 'forms/Index')->name('forms.index');
    Route::get('forms/{form}', fn (Form $form) => Inertia::render('forms/Show', [
        'formId' => $form->id,
    ]))->whereNumber('form')->name('forms.show');
});

Route::get('f/{form:public_id}', fn (Form $form) => Inertia::render('forms/Public', [
    'publicId' => $form->public_id,
]))->whereUuid('form')->name('forms.public');

require __DIR__.'/settings.php';
