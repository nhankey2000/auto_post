<?php

namespace App\Filament\Resources\PlatformAccountResource\Pages;

use App\Filament\Resources\PlatformAccountResource;
use Filament\Resources\Pages\Page;
use App\Models\PlatformAccount;
use Illuminate\Support\Facades\Log;

class AnalyticsPlatformAccount extends Page
{
    protected static string $resource = PlatformAccountResource::class;

    protected static string $view = 'filament.resources.platform-account-resource.pages.analytics-platform-account';

    public $record;

    public function mount(string $record): void
    {
        $this->record = PlatformAccount::findOrFail($record);
        Log::info('Resolved Record:', ['record' => $this->record->toArray()]);
    }

    public function getViewData(): array
    {
        return [
            'record' => $this->record,
            'headerWidgets' => $this->getHeaderWidgetsData(),
        ];
    }

    protected function getHeaderWidgetsData(): array
    {
        // Lấy tất cả bản ghi trong 7 ngày qua
        $analytics = $this->record->analytics()
            ->where('date', '>=', now()->subDays(7))
            ->get();

        // Tính tổng các giá trị bằng PHP
        $impressionsSum = $analytics->sum('impressions');
        $engagementsSum = $analytics->sum('engagements');
        $reachSum = $analytics->sum('reach');
        $linkClicksSum = $analytics->sum('link_clicks');

        // Lấy bản ghi mới nhất để lấy số người theo dõi
        $latestFollowers = $this->record->analytics()->latest('date')->first();

        return [
            [
                'title' => 'Lượt xem trang',
                'value' => $impressionsSum,
                'description' => 'Tổng lượt xem trong 7 ngày qua',
            ],
            [
                'title' => 'Người dùng tương tác',
                'value' => $engagementsSum,
                'description' => 'Tổng số người dùng tương tác trong 7 ngày qua',
            ],
            [
                'title' => 'Lượt tiếp cận',
                'value' => $reachSum,
                'description' => 'Tổng lượt tiếp cận trong 7 ngày qua',
            ],
            [
                'title' => 'Lượt nhấp liên kết',
                'value' => $linkClicksSum,
                'description' => 'Tổng số lượt nhấp liên kết trong 7 ngày qua',
            ],
            [
                'title' => 'Người theo dõi',
                'value' => $latestFollowers->followers_count ?? 0,
                'description' => 'Số người theo dõi hiện tại',
            ],
        ];
    }

    public function getWidgetName($configuration): string
    {
        // Phương thức này không còn cần thiết trong cách tiếp cận mới
        return '';
    }
}