<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\BucketController;
use App\Http\Controllers\FilesController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Bucket management routes
    Route::get('/buckets', [BucketController::class, 'index'])->name('buckets.index');
    Route::get('/buckets/create', [BucketController::class, 'create'])->name('buckets.create');
    Route::post('/buckets', [BucketController::class, 'store'])->name('buckets.store');
    Route::post('/buckets/{bucket}/test', [BucketController::class, 'test'])->name('buckets.test');
    Route::post('/buckets/{bucket}/activate', [BucketController::class, 'activate'])->name('buckets.activate');
    Route::delete('/buckets/{bucket}', [BucketController::class, 'destroy'])->name('buckets.destroy');
    
    // File management routes
    Route::get('/files', [FilesController::class, 'index'])->name('files.index');
    Route::get('/files/{bucket}/download', [FilesController::class, 'download'])->name('files.download');
    Route::delete('/files/{bucket}', [FilesController::class, 'destroy'])->name('files.destroy');
    Route::post('/files/upload-url', [FilesController::class, 'getUploadUrl'])->name('files.upload-url');
});

require __DIR__.'/auth.php';
