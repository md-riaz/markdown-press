<?php

use App\Http\Controllers\Web\PostController;
use App\Http\Controllers\Web\CategoryController;
use App\Http\Controllers\Web\TagController;
use App\Http\Controllers\Web\AuthorController;
use App\Http\Controllers\Web\PageViewController;
use App\Http\Controllers\Web\SubscribeController;
use App\Http\Controllers\Web\CommentController;
use Illuminate\Support\Facades\Route;

// Sitemap & robots
Route::get('/sitemap.xml', [PageViewController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt',  [PageViewController::class, 'robots'])->name('robots');

// Blog home
Route::get('/', [PostController::class, 'index'])->name('home');
Route::get('/page/{page}', [PostController::class, 'index'])->where('page', '[0-9]+')->name('home.page');

// Taxonomy
Route::get('/category/{slug}',   [CategoryController::class, 'show'])->name('category.show');
Route::get('/tag/{slug}',         [TagController::class, 'show'])->name('tag.show');
Route::get('/author/{username}',  [AuthorController::class, 'show'])->name('author.show');

// Newsletter
Route::post('/newsletter/subscribe',          [SubscribeController::class, 'subscribe'])->name('newsletter.subscribe');
Route::get('/newsletter/unsubscribe/{token}',  [SubscribeController::class, 'unsubscribe'])->name('newsletter.unsubscribe');

// Comments
Route::post('/posts/{slug}/comments', [CommentController::class, 'store'])->name('comments.store');

// Single post (must be last)
Route::get('/{slug}',         [PostController::class, 'show'])->name('post.show');
Route::post('/{slug}/unlock', [PostController::class, 'unlock'])->name('post.unlock');
