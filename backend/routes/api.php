<?php

use App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Api\Public;
use App\Http\Middleware\AllowProjectOrigin;
use App\Http\Middleware\EnsureProjectPublished;
use App\Models\Project;
use Illuminate\Support\Facades\Route;

// Widget and board, no auth. Priority and the hidden flag never leave these endpoints.
// EnsureProjectPublished closes a board until its owner confirms the email; it goes after AllowProjectOrigin,
// so the widget's 403 still carries CORS headers. The preflight (OPTIONS) stays open.
Route::prefix('public')->group(function () {
    Route::get('legal-documents', [Public\LegalDocumentController::class, 'index']);
    Route::get('projects/{project:slug}', [Public\ProjectController::class, 'show'])->middleware([AllowProjectOrigin::class, EnsureProjectPublished::class]);
    Route::options('projects/{project:slug}/posts', fn (Project $project) => response()->noContent())->middleware(AllowProjectOrigin::class);
    Route::post('projects/{project:slug}/posts', [Public\PostController::class, 'store'])->middleware([AllowProjectOrigin::class, EnsureProjectPublished::class, 'throttle:posts']);
    // the gate screen of a private board: outside EnsureProjectPublished on purpose, it's for those it keeps out
    Route::post('projects/{project:slug}/access-requests', [Public\AccessRequestController::class, 'store'])->middleware('throttle:access-requests');

    Route::middleware(EnsureProjectPublished::class)->group(function () {
        Route::get('projects/{project:slug}/posts', [Public\PostController::class, 'index']);
        Route::get('projects/{project:slug}/leaderboard', [Public\LeaderboardController::class, 'index']);
        Route::get('projects/{project:slug}/weekly-winners', [Public\WeeklyWinnersController::class, 'index']);
        Route::get('projects/{project:slug}/posts/{post}', [Public\PostController::class, 'show'])->scopeBindings();

        Route::post('posts/{post}/vote', [Public\VoteController::class, 'store'])->middleware('throttle:votes');
        Route::delete('posts/{post}/vote', [Public\VoteController::class, 'destroy'])->middleware('throttle:votes');

        Route::get('attachments/{attachment}', [Public\AttachmentController::class, 'show']);
    });
});

// Team mode of the board: Sanctum session.
Route::prefix('admin')->group(function () {
    Route::post('login', [Admin\AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('register', [Admin\RegistrationController::class, 'store'])->middleware('throttle:register');
    // the link from the letter; the SPA posts its path and query here (see AppServiceProvider)
    Route::post('email/verify/{user}/{hash}', [Admin\EmailVerificationController::class, 'verify'])
        ->middleware(['signed:relative', 'throttle:verify-email'])->name('verification.verify');
    // forgotten password: the letter, then the new password from the page it links to
    Route::post('forgot-password', [Admin\PasswordResetController::class, 'send'])->middleware('throttle:password-reset');
    Route::post('reset-password', [Admin\PasswordResetController::class, 'reset'])->middleware('throttle:password-reset');
    Route::post('reset-password/check', [Admin\PasswordResetController::class, 'check'])->middleware('throttle:password-reset');

    // Public invitation flow (no auth needed to read or accept — the token IS the credential).
    Route::get('invitations/{token}', [Admin\InvitationController::class, 'show']);
    Route::post('invitations/{token}/accept', [Admin\InvitationController::class, 'accept']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [Admin\AuthController::class, 'logout']);
        Route::get('me', [Admin\AuthController::class, 'me']);
        // «Вернуться в админку» — только для текущей impersonation-сессии; доступа к /admin отдельно не даёт.
        Route::post('impersonation/stop', [Admin\ImpersonationController::class, 'stop']);
        Route::patch('me', [Admin\AuthController::class, 'update']);
        Route::patch('me/password', [Admin\PasswordController::class, 'update'])->middleware('throttle:password-change');
        Route::post('email/verification-notification', [Admin\EmailVerificationController::class, 'resend'])->middleware('throttle:verify-email');

        // Self-serve boards owned by the current admin — drives the hub, create wizard and embed screen.
        Route::get('boards', [Admin\BoardController::class, 'index']);
        Route::post('boards', [Admin\BoardController::class, 'store']);
        Route::post('boards/check-slug', [Admin\BoardController::class, 'checkSlug']);
        Route::get('boards/{board:slug}/embed', [Admin\BoardController::class, 'embed']);
        Route::get('boards/{board:slug}', [Admin\BoardController::class, 'show']);
        Route::patch('boards/{board:slug}', [Admin\BoardController::class, 'update']);
        Route::delete('boards/{board:slug}', [Admin\BoardController::class, 'destroy']);
        Route::post('boards/{board:slug}/restore', [Admin\BoardController::class, 'restore'])->withTrashed();

        // Invitations addressed to me — «Вас приглашают» in the cabinet.
        Route::get('me/invitations', [Admin\IncomingInvitationController::class, 'index']);
        Route::post('me/invitations/{invitation}/accept', [Admin\IncomingInvitationController::class, 'accept']);
        Route::delete('me/invitations/{invitation}', [Admin\IncomingInvitationController::class, 'destroy']);

        // Team of a board: members pivot (role) and pending invitations.
        Route::get('boards/{board:slug}/participants', [Admin\ParticipantController::class, 'index']);
        Route::patch('boards/{board:slug}/participants/{user}', [Admin\ParticipantController::class, 'update']);
        Route::delete('boards/{board:slug}/participants/{user}', [Admin\ParticipantController::class, 'destroy']);

        Route::post('boards/{board:slug}/invitations', [Admin\InvitationController::class, 'store']);
        Route::post('boards/{board:slug}/invitations/{invitation}/resend', [Admin\InvitationController::class, 'resend']);
        Route::delete('boards/{board:slug}/invitations/{invitation}', [Admin\InvitationController::class, 'destroy']);

        // «Запросы на доступ»: the pending list comes with participants
        Route::post('boards/{board:slug}/access-requests/{accessRequest}/approve', [Admin\AccessRequestController::class, 'approve']);
        Route::post('boards/{board:slug}/access-requests/{accessRequest}/reject', [Admin\AccessRequestController::class, 'reject']);

        Route::get('projects/{project:slug}/posts', [Admin\PostController::class, 'index']);

        // Пачкой: массовый триаж из списка (чекбоксы + плавающая панель).
        // Выше posts/{post}, иначе DELETE posts/bulk уходит в destroy с {post}=bulk → 404.
        Route::post('posts/bulk/move', [Admin\PostController::class, 'bulkMove']);
        Route::post('posts/bulk/categorize', [Admin\PostController::class, 'bulkCategorize']);
        Route::post('posts/bulk/hide', [Admin\PostController::class, 'bulkHide']);
        Route::delete('posts/bulk', [Admin\PostController::class, 'bulkDelete']);

        Route::get('posts/{post}', [Admin\PostController::class, 'show']);
        Route::get('posts/{post}/voters', [Admin\PostController::class, 'voters']);
        Route::patch('posts/{post}', [Admin\PostController::class, 'update']);
        Route::patch('posts/{post}/order', [Admin\PostController::class, 'reorder']);
        Route::delete('posts/{post}', [Admin\PostController::class, 'destroy']);

        Route::get('attachments/{attachment}', [Admin\AttachmentController::class, 'show']);

        Route::post('projects/{project:slug}/categories', [Admin\CategoryController::class, 'store']);
        Route::patch('categories/{category}', [Admin\CategoryController::class, 'update']);
        Route::delete('categories/{category}', [Admin\CategoryController::class, 'destroy']);

        Route::post('projects/{project:slug}/statuses', [Admin\StatusController::class, 'store']);
        Route::patch('projects/{project:slug}/statuses/reorder', [Admin\StatusController::class, 'reorder']);
        Route::patch('projects/{project:slug}/statuses/default', [Admin\StatusController::class, 'setDefault']);
        Route::get('statuses/{status}/deletion', [Admin\StatusController::class, 'deletionInfo']);
        Route::patch('statuses/{status}', [Admin\StatusController::class, 'update']);
        Route::delete('statuses/{status}', [Admin\StatusController::class, 'destroy']);
    });
});
