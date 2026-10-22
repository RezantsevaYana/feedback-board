<?php

use App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Api\Public;
use App\Http\Middleware\AllowProjectOrigin;
use App\Models\Project;
use Illuminate\Support\Facades\Route;

// Widget and board, no auth. Priority and the hidden flag never leave these endpoints.
Route::prefix('public')->group(function () {
    Route::get('projects/{project:slug}', [Public\ProjectController::class, 'show'])->middleware(AllowProjectOrigin::class);
    Route::get('projects/{project:slug}/posts', [Public\PostController::class, 'index']);
    Route::get('projects/{project:slug}/leaderboard', [Public\LeaderboardController::class, 'index']);
    Route::get('projects/{project:slug}/weekly-winners', [Public\WeeklyWinnersController::class, 'index']);
    Route::options('projects/{project:slug}/posts', fn (Project $project) => response()->noContent())->middleware(AllowProjectOrigin::class);
    Route::post('projects/{project:slug}/posts', [Public\PostController::class, 'store'])->middleware([AllowProjectOrigin::class, 'throttle:posts']);
    Route::get('projects/{project:slug}/posts/{post}', [Public\PostController::class, 'show'])->scopeBindings();

    Route::post('posts/{post}/vote', [Public\VoteController::class, 'store'])->middleware('throttle:votes');
    Route::delete('posts/{post}/vote', [Public\VoteController::class, 'destroy'])->middleware('throttle:votes');

    Route::get('attachments/{attachment}', [Public\AttachmentController::class, 'show']);
});

// Team mode of the board: Sanctum session.
Route::prefix('admin')->group(function () {
    Route::post('login', [Admin\AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('register', [Admin\RegistrationController::class, 'store'])->middleware('throttle:register');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [Admin\AuthController::class, 'logout']);
        Route::get('me', [Admin\AuthController::class, 'me']);

        // Self-serve boards owned by the current admin — drives the hub, create wizard and embed screen.
        Route::get('boards', [Admin\BoardController::class, 'index']);
        Route::post('boards', [Admin\BoardController::class, 'store']);
        Route::post('boards/check-slug', [Admin\BoardController::class, 'checkSlug']);
        Route::get('boards/{board:slug}/embed', [Admin\BoardController::class, 'embed']);

        Route::get('projects/{project:slug}/posts', [Admin\PostController::class, 'index']);
        Route::get('posts/{post}', [Admin\PostController::class, 'show']);
        Route::patch('posts/{post}', [Admin\PostController::class, 'update']);
        Route::delete('posts/{post}', [Admin\PostController::class, 'destroy']);

        // Пачкой: массовый триаж из списка (чекбоксы + плавающая панель).
        Route::post('posts/bulk/move', [Admin\PostController::class, 'bulkMove']);
        Route::post('posts/bulk/categorize', [Admin\PostController::class, 'bulkCategorize']);
        Route::post('posts/bulk/hide', [Admin\PostController::class, 'bulkHide']);
        Route::delete('posts/bulk', [Admin\PostController::class, 'bulkDelete']);

        Route::get('attachments/{attachment}', [Admin\AttachmentController::class, 'show']);

        Route::post('projects/{project:slug}/categories', [Admin\CategoryController::class, 'store']);
        Route::patch('categories/{category}', [Admin\CategoryController::class, 'update']);
        Route::delete('categories/{category}', [Admin\CategoryController::class, 'destroy']);

        Route::post('projects/{project:slug}/statuses', [Admin\StatusController::class, 'store']);
        Route::patch('statuses/{status}', [Admin\StatusController::class, 'update']);
        Route::delete('statuses/{status}', [Admin\StatusController::class, 'destroy']);
    });
});
