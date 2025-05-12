<?php

namespace App\Filament\Resources\PlatformAccountResource\Pages;

use App\Filament\Resources\PlatformAccountResource;
use App\Filament\Widgets\AnalyticsChart;
use Filament\Resources\Pages\Page;
use App\Models\PageAnalytic;
use Carbon\Carbon;

class AnalyticsPage extends Page
{
    protected static string $resource = PlatformAccountResource::class;

    protected static ?string $title = 'Thống kê hiệu suất';

    protected function getViewData(): array
    {
        $platformAccountId = $this->record->id;
        $since = Carbon::today()->subDays(7)->toDateString();
        $until = Carbon::today()->toDateString();

        $analytics = PageAnalytic::where('platform_account_id', $platformAccountId)
            ->whereBetween('date', [$since, $until])
            ->orderBy('date', 'desc')
            ->first();

        return [
            'followers_count' => $analytics ? $analytics->followers_count : 0,
            'impressions' => $analytics ? $analytics->impressions : 0,
            'engagements' => $analytics ? $analytics->engagements : 0,
            'reach' => $analytics ? $analytics->reach : 0,
            'link_clicks' => $analytics ? $analytics->link_clicks : 0,
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AnalyticsChart::class,
        ];
    }
}