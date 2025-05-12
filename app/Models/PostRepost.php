<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostRepost extends Model
{
    protected $table = 'post_reposts';

    protected $fillable = [
        'post_id',
        'platform_account_ids',
        'reposted_at',
        'facebook_post_id',
        'platform_account_id', // Thay platform_account_ids thành platform_account_id
    ];

    protected $casts = [
        'platform_account_ids' => 'array',
        'reposted_at' => 'datetime',
    ];
    public function platformAccount()
    {
        return $this->belongsTo(PlatformAccount::class, 'platform_account_id');
    }
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
    public function aiPrompt(): BelongsTo
    {
        return $this->belongsTo(AiPostPrompt::class, 'post_id');
    }
}