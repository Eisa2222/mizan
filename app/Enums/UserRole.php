<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'SuperAdmin';
    case OrgAdmin = 'OrgAdmin';
    case LegalCounsel = 'LegalCounsel';
    case Researcher = 'Researcher';
    case OrgUser = 'OrgUser';
    case ReadOnly = 'ReadOnly';

    public function label(): string
    {
        return (string) __('roles.labels.' . $this->value);
    }

    public function description(): string
    {
        return (string) __('roles.descriptions.' . $this->value);
    }

    /** Hierarchy: higher rank includes all lower ones */
    public function rank(): int
    {
        return match ($this) {
            self::SuperAdmin   => 6,
            self::OrgAdmin     => 5,
            self::LegalCounsel => 4,
            self::Researcher   => 3,
            self::OrgUser      => 2,
            self::ReadOnly     => 1,
        };
    }

    /** Whether this role can upload legal documents (المستندات القانونية). */
    public function canUploadDocuments(): bool
    {
        return $this->rank() >= self::Researcher->rank();
    }

    public function isAtLeast(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($r) => [$r->value => $r->label()])->toArray();
    }

    /**
     * Dotted permissions granted to this role.
     *
     * SuperAdmin intentionally returns every Permission case — do not hand-maintain
     * its list; any new Permission is automatically granted. All other roles are
     * explicit so adding a case to the enum fails closed for them.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        if ($this === self::SuperAdmin) {
            return Permission::cases();
        }

        return match ($this) {
            self::OrgAdmin => [
                Permission::UsersView,          Permission::UsersCreate,
                Permission::UsersUpdate,        Permission::UsersDelete,
                Permission::OrganizationsView,  Permission::OrganizationsUpdate,
                Permission::DocumentsView,      Permission::DocumentsCreate,
                Permission::DocumentsUpdate,    Permission::DocumentsDelete,
                Permission::DocumentsUpload,
                Permission::ContractReviewsView, Permission::ContractReviewsCreate,
                Permission::MemosView,          Permission::MemosCreate,
                Permission::TasksView,          Permission::TasksCreate,
                Permission::TasksUpdate,        Permission::TasksDelete,
                Permission::TasksAssign,        Permission::TasksComment,
                Permission::FoldersView,        Permission::FoldersCreate,
                Permission::FoldersUpdate,      Permission::FoldersDelete,
                Permission::FoldersManageMembers,
                Permission::AnnotationsCreate,  Permission::AnnotationsDelete,
                Permission::DiscussionsCreate,  Permission::DiscussionsReply,
                Permission::DiscussionsDelete,
                Permission::WatchlistManage,
                Permission::NotificationsView,
                Permission::TendersView,        Permission::TendersCreate,
                Permission::TendersUpdate,      Permission::TendersDelete,
                Permission::TendersSubmit,      Permission::TendersApprove,
                Permission::TendersReject,      Permission::TendersExport,
                Permission::TenderReviewsView,  Permission::TenderReviewsCreate,
                Permission::BrandingUpdate,
                Permission::AssistantUse,       Permission::SearchUse,
                Permission::GpcKnowledgeView,
                Permission::ArticleUpdatesCreate, Permission::ArticleUpdatesDelete,
                Permission::RelationsCreate,    Permission::RelationsDelete,
                Permission::VersionsCreate,
            ],

            self::LegalCounsel => [
                Permission::DocumentsView,      Permission::DocumentsCreate,
                Permission::DocumentsUpdate,    Permission::DocumentsUpload,
                Permission::ContractReviewsView, Permission::ContractReviewsCreate,
                Permission::MemosView,          Permission::MemosCreate,
                Permission::TasksView,          Permission::TasksCreate,
                Permission::TasksUpdate,        Permission::TasksAssign,
                Permission::TasksComment,
                Permission::FoldersView,        Permission::FoldersCreate,
                Permission::FoldersUpdate,      Permission::FoldersManageMembers,
                Permission::AnnotationsCreate,  Permission::AnnotationsDelete,
                Permission::DiscussionsCreate,  Permission::DiscussionsReply,
                Permission::DiscussionsDelete,
                Permission::WatchlistManage,    Permission::NotificationsView,
                Permission::TendersView,        Permission::TendersCreate,
                Permission::TendersUpdate,      Permission::TendersSubmit,
                Permission::TendersApprove,     Permission::TendersReject,
                Permission::TendersExport,
                Permission::TenderReviewsView,  Permission::TenderReviewsCreate,
                Permission::AssistantUse,       Permission::SearchUse,
                Permission::GpcKnowledgeView,
                Permission::ArticleUpdatesCreate, Permission::ArticleUpdatesDelete,
                Permission::RelationsCreate,    Permission::RelationsDelete,
                Permission::VersionsCreate,
            ],

            self::Researcher => [
                Permission::DocumentsView,      Permission::DocumentsCreate,
                Permission::DocumentsUpload,
                Permission::ContractReviewsView, Permission::ContractReviewsCreate,
                Permission::MemosView,          Permission::MemosCreate,
                Permission::TasksView,          Permission::TasksCreate,
                Permission::TasksComment,
                Permission::FoldersView,        Permission::FoldersCreate,
                Permission::AnnotationsCreate,  Permission::AnnotationsDelete,
                Permission::DiscussionsCreate,  Permission::DiscussionsReply,
                Permission::WatchlistManage,    Permission::NotificationsView,
                Permission::TendersView,        Permission::TendersCreate,
                Permission::TendersSubmit,      Permission::TendersExport,
                Permission::TenderReviewsView,  Permission::TenderReviewsCreate,
                Permission::AssistantUse,       Permission::SearchUse,
                Permission::GpcKnowledgeView,
                Permission::ArticleUpdatesCreate,
                Permission::RelationsCreate,
                Permission::VersionsCreate,
            ],

            self::OrgUser => [
                Permission::DocumentsView,
                Permission::ContractReviewsView, Permission::ContractReviewsCreate,
                Permission::MemosView,          Permission::MemosCreate,
                Permission::TasksView,          Permission::TasksCreate,
                Permission::TasksComment,
                Permission::FoldersView,
                Permission::AnnotationsCreate,  Permission::AnnotationsDelete,
                Permission::DiscussionsCreate,  Permission::DiscussionsReply,
                Permission::WatchlistManage,    Permission::NotificationsView,
                Permission::TendersView,        Permission::TendersCreate,
                Permission::TendersSubmit,      Permission::TendersExport,
                Permission::TenderReviewsView,  Permission::TenderReviewsCreate,
                Permission::AssistantUse,       Permission::SearchUse,
                Permission::GpcKnowledgeView,
            ],

            self::ReadOnly => [
                Permission::DocumentsView,
                Permission::ContractReviewsView,
                Permission::MemosView,
                Permission::TasksView,
                Permission::FoldersView,
                Permission::NotificationsView,
                Permission::TendersView,
                Permission::TenderReviewsView,
                Permission::SearchUse,
                Permission::GpcKnowledgeView,
            ],

            self::SuperAdmin => Permission::cases(),
        };
    }

    public function hasPermission(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    public function hasPermissionString(string $permission): bool
    {
        $case = Permission::tryFrom($permission);

        return $case !== null && $this->hasPermission($case);
    }
}
