<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformAccount extends Model
{
    protected $fillable = [
        'platform_id',
        'name',
        'access_token',
        'page_id',
        'app_id',
        'app_secret',
        'access_token',
        'api_key',
        'api_secret',
        'extra_data',
    ];

    protected $casts = [
        'extra_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class, 'platform_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'platform_account_id');
    }

    public function reposts(): HasMany
    {
        return $this->hasMany(PostRepost::class, 'platform_account_id');
    }
    public function analytics(): HasMany
    {
        return $this->hasMany(PageAnalytic::class, 'platform_account_id', 'id');
    }
    public function pageAccessTokens()
    {
        return $this->hasMany(PageAccessToken::class);
    }
    
}