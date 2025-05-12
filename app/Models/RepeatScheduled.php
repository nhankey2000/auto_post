<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepeatScheduled extends Model
{
    protected $table = 'repeat_scheduled';

    protected $fillable = [
        'ai_post_prompts_id',
        'post_option',
        'selected_pages',
        'facebook_post_id',
        'schedule',
        'platform_account_id', // Thêm cột mới vào đây
        'title',       // Thêm cột title
        'content',     // Thêm cột content
        'images',      // Thêm cột images
        'videos', // Thêm cột videos vào fillable
    ];

    protected $casts = [
        'selected_pages' => 'array',
        'schedule' => 'datetime',
        'images' => 'array', // Đảm bảo cột images được cast thành mảng
        'videos' => 'array', // Cast cột videos thành array
    ];

    public function aiPostPrompt()
    {
        return $this->belongsTo(AiPostPrompt::class, 'ai_post_prompts_id');
    }

    public function getFirstImage()
    {
        if (!empty($this->images)) {
            return is_array($this->images) ? $this->images[0] : $this->images;
        }
        return null;
    }
    public function platformAccount()
    {
        return $this->belongsTo(PlatformAccount::class, 'platform_account_id');
    }
}