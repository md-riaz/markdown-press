<?php

use App\Http\Controllers\Api\V1\AIController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AuthorController;
use App\Http\Controllers\Api\V1\BuildController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\TagController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {

    // Auth
    Route::post('/auth/token', [AuthController::class, 'token'])->name('auth.token');
    Route::post('/auth/revoke', [AuthController::class, 'revoke'])->middleware('auth.apitoken')->name('auth.revoke');
    Route::get('/auth/tokens', [AuthController::class, 'tokens'])->middleware('auth.apitoken')->name('auth.tokens');

    // Public read
    Route::get('/posts', [PostController::class, 'index']);
    Route::get('/posts/{slug}', [PostController::class, 'show']);
    Route::get('/posts/{slug}/translations', [PostController::class, 'translations']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{slug}', [CategoryController::class, 'show']);
    Route::get('/tags', [TagController::class, 'index']);
    Route::get('/tags/{slug}', [TagController::class, 'show']);
    Route::get('/authors', [AuthorController::class, 'index']);
    Route::get('/authors/{username}', [AuthorController::class, 'show']);
    Route::get('/media/{id}', [MediaController::class, 'show']);

    // Protected
    Route::middleware('auth.apitoken')->group(function () {
        // Posts CRUD
        Route::post('/posts', [PostController::class, 'store']);
        Route::put('/posts/{slug}', [PostController::class, 'update']);
        Route::delete('/posts/{slug}', [PostController::class, 'destroy']);
        Route::post('/posts/{slug}/clone', [PostController::class, 'clone']);
        Route::get('/posts/{slug}/revisions', [PostController::class, 'revisions']);
        Route::post('/posts/{slug}/revisions/{id}/restore', [PostController::class, 'restoreRevision']);
        Route::post('/posts/{slug}/publish', [PostController::class, 'publish']);
        Route::post('/posts/{slug}/schedule', [PostController::class, 'schedule']);

        // Comments moderation
        Route::patch('/comments/{id}/approve', [CommentController::class, 'approve']);
        Route::patch('/comments/{id}/reject', [CommentController::class, 'reject']);
        Route::delete('/comments/{id}', [CommentController::class, 'destroy']);

        // Media
        Route::post('/media', [MediaController::class, 'store']);
        Route::delete('/media/{id}', [MediaController::class, 'destroy']);
        Route::patch('/media/{id}/star', [MediaController::class, 'star']);

        // AI
        Route::post('/ai/summary', [AIController::class, 'summary']);
        Route::post('/ai/excerpt', [AIController::class, 'excerpt']);
        Route::post('/ai/translate', [AIController::class, 'translate']);

        // SSG Builds
        Route::post('/builds', [BuildController::class, 'store']);
        Route::get('/builds', [BuildController::class, 'index']);
        Route::get('/builds/{id}', [BuildController::class, 'show']);
    });
});
