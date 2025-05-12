<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageAnalytic extends Model
{
    protected $table = 'page_analytics'; // Chỉ định tên bảng

    protected $fillable = [
        'platform_account_id',
        'date',
        'followers_count',
        'reach',
        'impressions',
        'engagements',
        'link_clicks',
    ];

    /**
     * Get the platform account that owns this analytic record.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function platformAccount()
    {
        return $this->belongsTo(PlatformAccount::class, 'platform_account_id');
    }
}