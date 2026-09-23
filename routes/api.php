<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DiscussionController;
use App\Http\Controllers\PublicationController;
use App\Http\Controllers\ResidentController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\WorkItemController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('activation/request', [AuthController::class, 'requestActivation']);
    Route::post('activation/confirm', [AuthController::class, 'confirmActivation']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('password/reset/request', [AuthController::class, 'requestPasswordReset']);
    Route::post('password/reset/confirm', [AuthController::class, 'confirmPasswordReset']);
});

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::get('auth/me', [AuthController::class, 'currentUser']);
    Route::post('users/import', [ResidentController::class, 'import'])->middleware('permission:users.manage');
    Route::patch('users/{user}/phone', [ResidentController::class, 'changePhone'])->middleware('permission:users.manage');

    Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.manage');
    Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.manage');
    Route::patch('roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.manage');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.manage');
    Route::put('roles/{role}/permissions', [RoleController::class, 'replacePermissions'])->middleware('permission:roles.manage');

    Route::get('work-items', [WorkItemController::class, 'index']);
    Route::post('work-items', [WorkItemController::class, 'store']);
    Route::get('work-items/{workItem}', [WorkItemController::class, 'show']);
    Route::patch('work-items/{workItem}', [WorkItemController::class, 'update']);
    Route::post('work-items/{workItem}/comments', [WorkItemController::class, 'comment']);
    Route::post('work-items/{workItem}/attachments', [WorkItemController::class, 'attachment']);
    Route::post('work-items/{workItem}/transition', [WorkItemController::class, 'transition']);
    Route::post('work-items/{workItem}/convert', [WorkItemController::class, 'convert']);
    Route::put('work-items/{workItem}/assignees', [WorkItemController::class, 'assignees']);
    Route::post('work-items/{workItem}/time-entries', [WorkItemController::class, 'timeEntry']);
    Route::post('work-items/{workItem}/budget-entries', [WorkItemController::class, 'budgetEntry']);

    Route::get('discussions', [DiscussionController::class, 'index']);
    Route::post('discussions', [DiscussionController::class, 'store']);
    Route::get('discussions/{discussion}', [DiscussionController::class, 'show']);
    Route::patch('discussions/{discussion}', [DiscussionController::class, 'update']);
    Route::post('discussions/{discussion}/members', [DiscussionController::class, 'members']);
    Route::post('discussions/{discussion}/messages', [DiscussionController::class, 'message']);
    Route::post('messages/{message}/like', [DiscussionController::class, 'like']);
    Route::post('discussions/{discussion}/verdict', [DiscussionController::class, 'verdict']);
    Route::post('discussions/{discussion}/close', [DiscussionController::class, 'close']);

    Route::get('news', [PublicationController::class, 'newsIndex']);
    Route::post('news', [PublicationController::class, 'newsStore']);
    Route::get('news/{news}', [PublicationController::class, 'newsShow']);
    Route::patch('news/{news}', [PublicationController::class, 'newsUpdate']);
    Route::post('news/{news}/publish', [PublicationController::class, 'newsPublish']);
    Route::post('news/{news}/unpublish', [PublicationController::class, 'newsUnpublish']);
    Route::post('news/{news}/attachments', [PublicationController::class, 'newsAttachment']);

    Route::get('infoboards', [PublicationController::class, 'infoboardIndex']);
    Route::post('infoboards', [PublicationController::class, 'infoboardStore']);
    Route::get('infoboards/{infoboard}', [PublicationController::class, 'infoboardShow']);
    Route::patch('infoboards/{infoboard}', [PublicationController::class, 'infoboardUpdate']);
    Route::post('infoboards/{infoboard}/publish', [PublicationController::class, 'infoboardPublish']);
    Route::post('infoboards/{infoboard}/unpublish', [PublicationController::class, 'infoboardUnpublish']);
    Route::post('infoboards/{infoboard}/attachments', [PublicationController::class, 'infoboardAttachment']);
});
