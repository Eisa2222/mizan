<?php

use App\Http\Controllers\AnnotationController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\ArticleUpdateController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\ContractReviewController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TenderReviewController;
use App\Http\Controllers\DiscussionController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\MemoController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RelationController;
use App\Http\Controllers\SearchPageController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TenderController;
use App\Http\Controllers\VersionController;
use App\Http\Controllers\WatchlistController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect(auth()->check() ? route('dashboard') : route('login'));
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Search API (web-session auth, returns JSON)
Route::middleware('auth')->get('/api/v1/search', SearchController::class)->name('api.search');
Route::middleware('auth')->get('/search', SearchPageController::class)->name('search');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Contract Reviews (standalone pages — fully separated from documents)
    Route::get('/contract-reviews', [ContractReviewController::class, 'index'])->name('contract-reviews.index');
    Route::get('/contract-reviews/create', [ContractReviewController::class, 'create'])->name('contract-reviews.create');
    Route::post('/contract-reviews', [ContractReviewController::class, 'store'])->name('contract-reviews.store');
    Route::get('/contract-reviews/{document}', [ContractReviewController::class, 'show'])->name('contract-reviews.show');

    // Memo Drafts (standalone pages — fully separated from documents)
    Route::get('/memos', [MemoController::class, 'index'])->name('memos.index');
    Route::get('/memos/create', [MemoController::class, 'create'])->name('memos.create');
    Route::post('/memos', [MemoController::class, 'store'])->name('memos.store');
    Route::get('/memos/{document}', [MemoController::class, 'show'])->name('memos.show');

    // Smart Tender Generator (مولّد الكراسات الذكي)
    Route::get('/tenders', [TenderController::class, 'index'])->name('tenders.index');
    Route::get('/tenders/create', [TenderController::class, 'create'])->name('tenders.create');
    Route::post('/tenders', [TenderController::class, 'store'])->name('tenders.store');
    Route::get('/tenders/{tender}', [TenderController::class, 'show'])->name('tenders.show');
    Route::patch('/tenders/{tender}/sections/{sectionId}', [TenderController::class, 'updateSection'])->name('tenders.updateSection');
    Route::post('/tenders/{tender}/regenerate', [TenderController::class, 'regenerate'])->name('tenders.regenerate');
    Route::post('/tenders/{tender}/review', [TenderController::class, 'review'])->name('tenders.review');
    Route::get('/tenders/{tender}/export/pdf', [TenderController::class, 'exportPdf'])->name('tenders.export.pdf');
    Route::get('/tenders/{tender}/export/docx', [TenderController::class, 'exportDocx'])->name('tenders.export.docx');
    Route::delete('/tenders/{tender}', [TenderController::class, 'destroy'])->name('tenders.destroy');

    // Tender Reviews (كراسات الشروط والمواصفات)
    Route::get('/tender-reviews', [TenderReviewController::class, 'index'])->name('tender-reviews.index');
    Route::get('/tender-reviews/create', [TenderReviewController::class, 'create'])->name('tender-reviews.create');
    Route::post('/tender-reviews', [TenderReviewController::class, 'store'])->name('tender-reviews.store');
    Route::get('/tender-reviews/{document}', [TenderReviewController::class, 'show'])->name('tender-reviews.show');

    // Documents
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/create', [DocumentController::class, 'create'])->name('documents.create');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::patch('/documents/{document}/content', [DocumentController::class, 'updateContent'])->name('documents.updateContent');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    // Tasks
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.status');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::post('/tasks/{task}/assign', [TaskController::class, 'assign'])->name('tasks.assign');
    Route::delete('/tasks/{task}/assign/{user}', [TaskController::class, 'unassign'])->name('tasks.unassign');
    Route::post('/tasks/{task}/comments', [TaskController::class, 'comment'])->name('tasks.comment');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.readAll');

    // Folders
    Route::get('/folders', [FolderController::class, 'index'])->name('folders.index');
    Route::get('/folders/create', [FolderController::class, 'create'])->name('folders.create');
    Route::post('/folders', [FolderController::class, 'store'])->name('folders.store');
    Route::get('/folders/{folder}', [FolderController::class, 'show'])->name('folders.show');
    Route::delete('/folders/{folder}', [FolderController::class, 'destroy'])->name('folders.destroy');
    Route::post('/folders/{folder}/members', [FolderController::class, 'addMember'])->name('folders.addMember');
    Route::delete('/folders/{folder}/members/{user}', [FolderController::class, 'removeMember'])->name('folders.removeMember');
    Route::post('/folders/{folder}/documents', [FolderController::class, 'uploadDocument'])->name('folders.uploadDocument');
    Route::delete('/folders/{folder}/documents/{document}', [FolderController::class, 'removeDocument'])->name('folders.removeDocument');

    // Watchlist
    Route::get('/watchlist', [WatchlistController::class, 'index'])->name('watchlist.index');
    Route::post('/documents/{document}/watch', [WatchlistController::class, 'toggle'])->name('watchlist.toggle');

    // Annotations
    Route::post('/documents/{document}/annotations', [AnnotationController::class, 'store'])->name('annotations.store');
    Route::delete('/annotations/{annotation}', [AnnotationController::class, 'destroy'])->name('annotations.destroy');

    // Discussions
    Route::post('/documents/{document}/discussions', [DiscussionController::class, 'store'])->name('discussions.store');
    Route::get('/discussions/{discussion}', [DiscussionController::class, 'show'])->name('discussions.show');
    Route::post('/discussions/{discussion}/reply', [DiscussionController::class, 'reply'])->name('discussions.reply');
    Route::delete('/discussions/{discussion}', [DiscussionController::class, 'destroy'])->name('discussions.destroy');

    // Article updates (manual entries — auto ones come from DiffDocumentVersionJob)
    Route::get('/documents/{document}/article-updates', [ArticleUpdateController::class, 'index'])->name('article-updates.index');
    Route::post('/documents/{document}/article-updates', [ArticleUpdateController::class, 'store'])->name('article-updates.store');
    Route::delete('/article-updates/{articleUpdate}', [ArticleUpdateController::class, 'destroy'])->name('article-updates.destroy');

    // Document versions (re-uploads that trigger an article-level diff)
    Route::post('/documents/{document}/versions', [VersionController::class, 'store'])->name('versions.store');

    // Document relations (نظام ↔ لائحة، مادة ↔ حكم، إلخ)
    Route::post('/documents/{document}/relations', [RelationController::class, 'store'])->name('relations.store');
    Route::delete('/relations/{relation}', [RelationController::class, 'destroy'])->name('relations.destroy');

    // Title-prefix autocomplete used by the "link related document" modal
    Route::get('/api/v1/documents/autocomplete', [DocumentController::class, 'autocomplete'])->name('documents.autocomplete');

    // AI Assistant (chat panel + RAG over a document)
    Route::post('/ai/conversations', [AssistantController::class, 'start'])->name('ai.start');
    Route::get('/ai/conversations/{conversation}', [AssistantController::class, 'show'])->name('ai.show');
    Route::post('/ai/conversations/{conversation}/messages', [AssistantController::class, 'sendMessage'])->name('ai.send');
});

require __DIR__.'/auth.php';
