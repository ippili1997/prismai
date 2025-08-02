<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\BucketController;
use App\Http\Controllers\FilesController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('buckets.index');
    }
    
    return redirect()->route('login');
});

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
    Route::patch('/buckets/{bucket}/rename', [BucketController::class, 'rename'])->name('buckets.rename');
    Route::delete('/buckets/{bucket}', [BucketController::class, 'destroy'])->name('buckets.destroy');
    
    // File management routes
    Route::get('/buckets/{bucket}/files', [FilesController::class, 'index'])->name('files.index');
    Route::get('/files/{bucket}/download', [FilesController::class, 'download'])->name('files.download');
    Route::delete('/files/{bucket}', [FilesController::class, 'destroy'])->name('files.destroy');
    Route::post('/buckets/{bucket}/upload-url', [FilesController::class, 'getUploadUrl'])->name('files.upload-url');
    Route::post('/buckets/{bucket}/view-url', [FilesController::class, 'getViewUrl'])->name('files.view-url');
    Route::post('/buckets/{bucket}/folder-download-urls', [FilesController::class, 'getFolderDownloadUrls'])->name('files.folder-download-urls');
    Route::post('/buckets/{bucket}/create-folder', [FilesController::class, 'createFolder'])->name('files.create-folder');
    Route::post('/buckets/{bucket}/rename', [FilesController::class, 'rename'])->name('files.rename');
    Route::get('/buckets/{bucket}/folder-tree', [FilesController::class, 'getFolderTree'])->name('files.folder-tree');
    Route::post('/buckets/{bucket}/move', [FilesController::class, 'move'])->name('files.move');
});

require __DIR__.'/auth.php';
