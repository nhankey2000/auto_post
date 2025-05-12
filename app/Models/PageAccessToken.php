<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageAccessToken extends Model
{
    protected $fillable = [
        'platform_account_id',
        'page_id',
        'page_name',
        'access_token',
    ];

    public function platformAccount()
    {
        return $this->belongsTo(PlatformAccount::class);
    }
}