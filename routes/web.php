<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (Request $request) => redirect()->route(
    $request->user() ? 'dashboard' : 'login',
))->name('home');

Route::middleware(['auth'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::inertia('forms/create', 'forms/Create')->name('forms.create');
});

require __DIR__.'/settings.php';
