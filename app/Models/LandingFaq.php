<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingFaq extends Model
{
    protected $table = 'landing_faqs';

    protected $fillable = ['question', 'answer', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive($q)
    {
        return $q->where('is_active', true)->orderBy('sort_order');
    }
}
