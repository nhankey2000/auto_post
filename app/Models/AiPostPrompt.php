<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiPostPrompt extends Model
{
    protected $fillable = [
        'prompt',
        'platform_id', // Chỉ giữ platform_id
        'image_category',
        'image',
        'image_count',
        'scheduled_at',
       
        'status',
        'generated_content',
        'post_option', // Thêm cột mới
        'selected_pages', // Thêm cột mới
        'title', // Thêm cột title
        'hashtags', // Thêm cột hashtags
        'posted_at',
    ];

    protected $casts = [
        'image' => 'array',
        'scheduled_at' => 'datetime',
        'posted_at' => 'datetime',
      
        'selected_pages' => 'array', // Chuyển đổi JSON thành mảng
        'hashtags' => 'array', // Cast hashtags thành array
    ];

    public function platform()
    {
        return $this->belongsTo(Platform::class, 'platform_id');
    }
    public function category()
    {
        return $this->belongsTo(Category::class, 'image_category');
    }
    public function repeatSchedules()
    {
        return $this->hasMany(RepeatScheduled::class, 'ai_post_prompts_id');
    }
}