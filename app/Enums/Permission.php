<?php

namespace App\Enums;

/**
 * Dotted permission names used everywhere authorization is checked.
 *
 * A role's permissions are declared in `UserRole::permissions()`. At runtime,
 * `Gate::before` (registered in AppServiceProvider) short-circuits any
 * `can()` call whose ability string matches one of these values, so:
 *
 *     $user->can('documents.view');
 *     @can('tenders.approve')
 *     Gate::allows(Permission::TendersApprove->value);
 *
 * all resolve against the role→permission map without hitting a policy.
 *
 * Policies still run for anything model-scoped (per-row ownership, same-org,
 * private docs); permissions answer "can this role ever do X", policies
 * answer "can this user do X to THIS specific record".
 */
enum Permission: string
{
    // Admin panel
    case AdminAccess            = 'admin.access';

    // Users (admin-managed)
    case UsersView              = 'users.view';
    case UsersCreate            = 'users.create';
    case UsersUpdate            = 'users.update';
    case UsersDelete            = 'users.delete';

    // Organizations (admin-managed)
    case OrganizationsView      = 'organizations.view';
    case OrganizationsCreate    = 'organizations.create';
    case OrganizationsUpdate    = 'organizations.update';
    case OrganizationsDelete    = 'organizations.delete';

    // Legal documents
    case DocumentsView          = 'documents.view';
    case DocumentsCreate        = 'documents.create';
    case DocumentsUpdate        = 'documents.update';
    case DocumentsDelete        = 'documents.delete';
    case DocumentsUpload        = 'documents.upload';

    // Contract reviews
    case ContractReviewsView    = 'contract_reviews.view';
    case ContractReviewsCreate  = 'contract_reviews.create';

    // Memos
    case MemosView              = 'memos.view';
    case MemosCreate            = 'memos.create';

    // Tasks
    case TasksView              = 'tasks.view';
    case TasksCreate            = 'tasks.create';
    case TasksUpdate            = 'tasks.update';
    case TasksDelete            = 'tasks.delete';
    case TasksAssign            = 'tasks.assign';
    case TasksComment           = 'tasks.comment';

    // Folders
    case FoldersView            = 'folders.view';
    case FoldersCreate          = 'folders.create';
    case FoldersUpdate          = 'folders.update';
    case FoldersDelete          = 'folders.delete';
    case FoldersManageMembers   = 'folders.manage_members';

    // Annotations / Discussions / Watchlist
    case AnnotationsCreate      = 'annotations.create';
    case AnnotationsDelete      = 'annotations.delete';
    case DiscussionsCreate      = 'discussions.create';
    case DiscussionsReply       = 'discussions.reply';
    case DiscussionsDelete      = 'discussions.delete';
    case WatchlistManage        = 'watchlist.manage';

    // Notifications
    case NotificationsView      = 'notifications.view';

    // Tenders
    case TendersView            = 'tenders.view';
    case TendersCreate          = 'tenders.create';
    case TendersUpdate          = 'tenders.update';
    case TendersDelete          = 'tenders.delete';
    case TendersSubmit          = 'tenders.submit';
    case TendersApprove         = 'tenders.approve';
    case TendersReject          = 'tenders.reject';
    case TendersExport          = 'tenders.export';

    // Tender reviews
    case TenderReviewsView      = 'tender_reviews.view';
    case TenderReviewsCreate    = 'tender_reviews.create';

    // Branding
    case BrandingUpdate         = 'branding.update';

    // AI assistant
    case AssistantUse           = 'assistant.use';

    // Search
    case SearchUse              = 'search.use';

    // GPC knowledge base
    case GpcKnowledgeView       = 'gpc_knowledge.view';

    // Article updates / Relations / Versions (document sub-features)
    case ArticleUpdatesCreate   = 'article_updates.create';
    case ArticleUpdatesDelete   = 'article_updates.delete';
    case RelationsCreate        = 'relations.create';
    case RelationsDelete        = 'relations.delete';
    case VersionsCreate         = 'versions.create';
}
