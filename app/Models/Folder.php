<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Folder extends Model
{
    protected $fillable = ['org_id', 'name', 'description', 'owner_id', 'parent_id'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Folder::class, 'parent_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(FolderMember::class);
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(LegalDocument::class, 'folder_documents', 'folder_id', 'document_id')
            ->withPivot('added_by')
            ->withTimestamps();
    }

    /** Whether the user has access (owner, member, or org-wide for admins) */
    public function isAccessibleBy(User $user): bool
    {
        return $this->owner_id === $user->id
            || $this->members()->where('user_id', $user->id)->exists()
            || ($user->hasAtLeastRole(\App\Enums\UserRole::OrgAdmin) && $this->org_id === $user->org_id);
    }
}
