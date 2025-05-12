<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    protected $fillable = [
        'title',
        'content',
        'media',
        'hashtags',
        'status',
        'scheduled_at',
        'facebook_post_id',
        'platform_account_id',
    ];

    protected $casts = [
        'media' => 'array',
        'hashtags' => 'array',
        'scheduled_at' => 'datetime',
    ];

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class, 'platform_account_id');
    }

    public function reposts(): HasMany
    {
        return $this->hasMany(PostRepost::class, 'post_id');
    }
}