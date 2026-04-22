<?php

use Modules\Annotations\Http\Controllers\AnnotationController;
use Modules\Search\Http\Controllers\SearchController;
use Modules\ArticleUpdates\Http\Controllers\ArticleUpdateController;
use Modules\Assistant\Http\Controllers\AssistantController;
use Modules\ContractReviews\Http\Controllers\ContractReviewController;
use Modules\Dashboard\Http\Controllers\DashboardController;
use Modules\TenderReviews\Http\Controllers\TenderReviewController;
use Modules\Discussions\Http\Controllers\DiscussionController;
use Modules\Documents\Http\Controllers\DocumentController;
use Modules\Memos\Http\Controllers\MemoController;
use Modules\Folders\Http\Controllers\FolderController;
use Modules\GpcKnowledge\Http\Controllers\GpcKnowledgeController;
use Modules\Notifications\Http\Controllers\NotificationController;
use Modules\Profile\Http\Controllers\ProfileController;
use Modules\Relations\Http\Controllers\RelationController;
use Modules\Search\Http\Controllers\SearchPageController;
use Modules\Tasks\Http\Controllers\TaskController;
use Modules\Admin\Http\Controllers\OrganizationController as AdminOrganizationController;
use Modules\Admin\Http\Controllers\UserController as AdminUserController;
use Modules\Tenders\Http\Controllers\TenderSimilarityController as SimilarityController;
use Modules\Branding\Http\Controllers\BrandingController;
use Modules\Tenders\Http\Controllers\TenderController;
use Modules\Versions\Http\Controllers\VersionController;
use Modules\Watchlist\Http\Controllers\WatchlistController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\MoyasarWebhookController;
use App\Http\Controllers\TenantPasswordSetupController;
use Modules\SuperAdmin\Http\Controllers\Auth\AuthenticatedSessionController as SuperAdminSessionController;
use Modules\SuperAdmin\Http\Controllers\CouponController;
use Modules\SuperAdmin\Http\Controllers\DashboardController as SuperAdminDashboardController;
use Modules\SuperAdmin\Http\Controllers\LandingFaqController;
use Modules\SuperAdmin\Http\Controllers\LandingFeatureController;
use Modules\SuperAdmin\Http\Controllers\PaymentController as SuperAdminPaymentController;
use Modules\SuperAdmin\Http\Controllers\PlanController;
use Modules\SuperAdmin\Http\Controllers\SettingController;
use Modules\SuperAdmin\Http\Controllers\SubscriptionController;
use Modules\SuperAdmin\Http\Controllers\TenantController as SuperAdminTenantController;
use Illuminate\Support\Facades\Route;

// Public landing page — renders from DB (hero, features, plans, faqs).
Route::get('/', LandingController::class)->name('landing');

// Checkout flow (public, central). Moyasar webhook is CSRF-exempted
// via bootstrap/app.php in the next block.
Route::get('/checkout/{plan}',      [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout/apply-coupon', [CheckoutController::class, 'applyCoupon'])->name('checkout.apply-coupon');
Route::get('/checkout/callback',    [CheckoutController::class, 'callback'])->name('checkout.callback');
Route::get('/checkout/success',     [CheckoutController::class, 'success'])->name('checkout.success');

// Moyasar webhook (server-to-server). CSRF is disabled for this path.
Route::post('/webhooks/moyasar', MoyasarWebhookController::class)->name('webhooks.moyasar');

// Tenant admin password setup lives on the tenant subdomain in
// routes/tenant.php — kept out of central so the password write hits
// the tenant DB, not the central connection.

// Tenant / authenticated dashboard redirect — / is now landing, so send
// app traffic through /app instead.
Route::get('/app', function () {
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

    // Admin Panel (SuperAdmin only)
    Route::middleware('super-admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/organizations', [AdminOrganizationController::class, 'index'])->name('organizations');
        Route::get('/organizations/create', [AdminOrganizationController::class, 'create'])->name('organizations.create');
        Route::post('/organizations', [AdminOrganizationController::class, 'store'])->name('organizations.store');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users');
        Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}/role', [AdminUserController::class, 'updateRole'])->name('users.update-role');
    });

    // Organization Branding (هوية المؤسسة)
    Route::get('/branding', [BrandingController::class, 'edit'])->name('branding.edit');
    Route::put('/branding', [BrandingController::class, 'update'])->name('branding.update');
    Route::delete('/branding/logo', [BrandingController::class, 'removeLogo'])->name('branding.remove-logo');

    // GPC Knowledge Base (نظام المنافسات + اللائحة + الأدلة)
    Route::get('/gpc-knowledge', [GpcKnowledgeController::class, 'index'])->name('gpc-knowledge.index');

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
    Route::post('/tenders/{tender}/submit', [TenderController::class, 'submit'])->name('tenders.submit');
    Route::post('/tenders/{tender}/approve', [TenderController::class, 'approve'])->name('tenders.approve');
    Route::post('/tenders/{tender}/reject', [TenderController::class, 'reject'])->name('tenders.reject');
    Route::delete('/tenders/{tender}', [TenderController::class, 'destroy'])->name('tenders.destroy');

    // Tender Similarity API
    Route::post('/api/v1/tenders/{tender}/similarity/analyze', [SimilarityController::class, 'analyze'])->name('api.similarity.analyze');
    Route::post('/api/v1/tenders/similarity/check-scope', [SimilarityController::class, 'checkScope'])->name('api.similarity.check-scope');
    Route::get('/api/v1/tenders/{tender}/similarity/results', [SimilarityController::class, 'results'])->name('api.similarity.results');
    Route::get('/api/v1/tenders/{tender}/similarity/compare/{matchedTender}', [SimilarityController::class, 'compare'])->name('api.similarity.compare');
    Route::post('/api/v1/tenders/{tender}/similarity/ignore', [SimilarityController::class, 'ignore'])->name('api.similarity.ignore');
    Route::post('/api/v1/tenders/{tender}/reuse/{matchedTender}', [SimilarityController::class, 'reuse'])->name('api.similarity.reuse');

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
    Route::get('/documents/{document}/read', [DocumentController::class, 'read'])->name('documents.read');
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

/*
|--------------------------------------------------------------------------
| SuperAdmin (SaaS Operators)
|--------------------------------------------------------------------------
| Separate guard, separate cookies, separate login. Nothing here ever
| touches tenant data directly — reads cross-tenant from central tables
| only (tenants, subscriptions, payments, coupons, plans).
*/
Route::prefix('super-admin')->name('super-admin.')->group(function () {

    // Guest — login form
    Route::middleware('guest:super_admin')->group(function () {
        Route::get('/login',  [SuperAdminSessionController::class, 'create'])->name('login');
        Route::post('/login', [SuperAdminSessionController::class, 'store'])->name('login.store');
    });

    // Authenticated SuperAdmin area
    Route::middleware('super-admin.auth')->group(function () {
        Route::post('/logout', [SuperAdminSessionController::class, 'destroy'])->name('logout');

        Route::get('/', SuperAdminDashboardController::class)->name('dashboard');

        // Profile self-service
        Route::get('/profile',            [\Modules\SuperAdmin\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile',          [\Modules\SuperAdmin\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
        Route::patch('/profile/password', [\Modules\SuperAdmin\Http\Controllers\ProfileController::class, 'updatePassword'])->name('profile.password');

        // Tenants
        Route::get('/tenants',                          [SuperAdminTenantController::class, 'index'])->name('tenants.index');
        Route::get('/tenants/{tenant}',                 [SuperAdminTenantController::class, 'show'])->name('tenants.show');
        Route::post('/tenants/{tenant}/suspend',        [SuperAdminTenantController::class, 'suspend'])->name('tenants.suspend');
        Route::post('/tenants/{tenant}/activate',       [SuperAdminTenantController::class, 'activate'])->name('tenants.activate');
        Route::delete('/tenants/{tenant}',              [SuperAdminTenantController::class, 'destroy'])->name('tenants.destroy');
        Route::post('/tenants/{tenant}/change-plan',    [SuperAdminTenantController::class, 'changePlan'])->name('tenants.change-plan');
        Route::post('/tenants/{tenant}/extend',         [SuperAdminTenantController::class, 'extend'])->name('tenants.extend');
        Route::post('/tenants/{tenant}/impersonate/{userId}', [SuperAdminTenantController::class, 'impersonate'])->name('tenants.impersonate');

        // Plans
        Route::get('/plans',                   [PlanController::class, 'index'])->name('plans.index');
        Route::get('/plans/create',            [PlanController::class, 'create'])->name('plans.create');
        Route::post('/plans',                  [PlanController::class, 'store'])->name('plans.store');
        Route::get('/plans/{plan}/edit',       [PlanController::class, 'edit'])->name('plans.edit');
        Route::put('/plans/{plan}',            [PlanController::class, 'update'])->name('plans.update');
        Route::post('/plans/{plan}/toggle',    [PlanController::class, 'toggle'])->name('plans.toggle');
        Route::delete('/plans/{plan}',         [PlanController::class, 'destroy'])->name('plans.destroy');

        // Subscriptions
        Route::get('/subscriptions',                   [SubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::post('/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');

        // Payments
        Route::get('/payments',                  [SuperAdminPaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/export',           [SuperAdminPaymentController::class, 'export'])->name('payments.export');
        Route::post('/payments/{payment}/refund', [SuperAdminPaymentController::class, 'refund'])->name('payments.refund');

        // Audit log
        Route::get('/audit',        [\Modules\SuperAdmin\Http\Controllers\AuditController::class, 'index'])->name('audit.index');
        Route::get('/audit/export', [\Modules\SuperAdmin\Http\Controllers\AuditController::class, 'export'])->name('audit.export');

        // Coupons
        Route::get('/coupons',                  [CouponController::class, 'index'])->name('coupons.index');
        Route::get('/coupons/create',           [CouponController::class, 'create'])->name('coupons.create');
        Route::get('/coupons/generate',         [CouponController::class, 'generate'])->name('coupons.generate');
        Route::post('/coupons',                 [CouponController::class, 'store'])->name('coupons.store');
        Route::get('/coupons/{coupon}',         [CouponController::class, 'show'])->name('coupons.show');
        Route::get('/coupons/{coupon}/edit',    [CouponController::class, 'edit'])->name('coupons.edit');
        Route::put('/coupons/{coupon}',         [CouponController::class, 'update'])->name('coupons.update');
        Route::post('/coupons/{coupon}/toggle', [CouponController::class, 'toggle'])->name('coupons.toggle');
        Route::delete('/coupons/{coupon}',      [CouponController::class, 'destroy'])->name('coupons.destroy');

        // Landing CMS
        Route::prefix('landing')->name('landing.')->group(function () {
            Route::get('/features',                        [LandingFeatureController::class, 'index'])->name('features.index');
            Route::post('/features',                       [LandingFeatureController::class, 'store'])->name('features.store');
            Route::put('/features/{feature}',              [LandingFeatureController::class, 'update'])->name('features.update');
            Route::delete('/features/{feature}',           [LandingFeatureController::class, 'destroy'])->name('features.destroy');
            Route::post('/features/reorder',               [LandingFeatureController::class, 'reorder'])->name('features.reorder');

            Route::get('/faqs',                            [LandingFaqController::class, 'index'])->name('faqs.index');
            Route::post('/faqs',                           [LandingFaqController::class, 'store'])->name('faqs.store');
            Route::put('/faqs/{faq}',                      [LandingFaqController::class, 'update'])->name('faqs.update');
            Route::delete('/faqs/{faq}',                   [LandingFaqController::class, 'destroy'])->name('faqs.destroy');
            Route::post('/faqs/reorder',                   [LandingFaqController::class, 'reorder'])->name('faqs.reorder');
        });

        // Settings
        Route::get('/settings',              [SettingController::class, 'index'])->name('settings.index');
        Route::put('/settings',              [SettingController::class, 'update'])->name('settings.update');
        Route::post('/settings/test-mail',   [SettingController::class, 'testMail'])->name('settings.test-mail');
        Route::post('/settings/test-moyasar', [SettingController::class, 'testMoyasar'])->name('settings.test-moyasar');
    });
});
