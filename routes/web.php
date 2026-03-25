<?php

use App\Http\Controllers\ImageGeneratorController;
use App\Http\Controllers\PolicyQuestionController;
use App\Http\Controllers\RagQuestionController;
use App\Http\Controllers\VideoSummaryController;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

// echo Hash::make('12345678');
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('image-generator', function () {
    return Inertia::render('ImageGenerator');
})->middleware(['auth', 'verified'])->name('image-generator');

Route::get('summary', function () {
    return Inertia::render('VideoSummary');
})->middleware(['auth', 'verified'])->name('summary');

Route::get('policy-qa', function () {
    return Inertia::render('PolicyQa');
})->middleware(['auth', 'verified'])->name('policy-qa');

Route::get('rag-qa', function () {
    return Inertia::render('RagQa');
})->middleware(['auth', 'verified'])->name('rag-qa');

Route::post('video-summary', VideoSummaryController::class)
    ->middleware(['auth', 'verified'])
    ->name('video-summary');

Route::post('policy-question', PolicyQuestionController::class)
    ->middleware(['auth', 'verified'])
    ->name('policy-question');

Route::post('rag-question', RagQuestionController::class)
    ->middleware(['auth', 'verified'])
    ->name('rag-question');

Route::post('generate-image', ImageGeneratorController::class)
    ->middleware(['auth', 'verified'])
    ->name('generate-image');

require __DIR__.'/settings.php';
