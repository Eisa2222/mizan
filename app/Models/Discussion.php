<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Discussion extends Model
{
    protected $fillable = ['document_id', 'user_id', 'title', 'body', 'visibility'];

    public const VISIBILITY_PUBLIC  = 'public';
    public const VISIBILITY_ORG     = 'org';
    public const VISIBILITY_PRIVATE = 'private';

    public const VISIBILITIES = [
        self::VISIBILITY_PUBLIC  => 'عامة — للجميع',
        self::VISIBILITY_ORG     => 'مقيّدة — المؤسسة',
        self::VISIBILITY_PRIVATE => 'خاصة — لي فقط',
    ];

    /** Scope: only discussions visible to the given user. */
    public function scopeVisibleTo($query, \App\Models\User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('visibility', self::VISIBILITY_PUBLIC)
              ->orWhere(function ($q2) use ($user) {
                  $q2->where('visibility', self::VISIBILITY_ORG)
                     ->whereHas('document', fn ($d) => $d->where('org_id', $user->org_id));
              })
              ->orWhere(function ($q2) use ($user) {
                  $q2->where('visibility', self::VISIBILITY_PRIVATE)
                     ->where('user_id', $user->id);
              });
        });
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'document_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(DiscussionReply::class);
    }
}
