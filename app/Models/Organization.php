<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'name_ar', 'name_en', 'domain',
        'logo_path', 'header_text', 'footer_text',
        'primary_color', 'accent_color',
        'phone', 'email', 'website', 'address',
    ];

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path
            ? asset('storage/' . $this->logo_path)
            : null;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'org_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(LegalDocument::class, 'org_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'org_id');
    }
}
