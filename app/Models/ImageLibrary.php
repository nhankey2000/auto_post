<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImageLibrary extends Model
{
    protected $table = 'image_library';

    protected $fillable = [
        'category_id',
        'item',
        'type',
    ];

    // protected $casts = [
    //     'img' => 'array', // Cast cột img thành mảng
    // ];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}   