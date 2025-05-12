<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\PageAnalytic;
use Carbon\Carbon;
use Illuminate\Support\Facades\Request;
use Filament\Forms;

class AnalyticsChart extends ChartWidget
{
    protected static ?string $heading = 'Biểu đồ tăng trưởng';

    public ?array $filters = [];

    public function getPlatformAccountId()
    {
        return Request::segment(3);
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\DatePicker::make('since')
                ->label('Từ ngày')
                ->default('2025-04-05'),
            Forms\Components\DatePicker::make('until')
                ->label('Đến ngày')
                ->default('2025-04-12'),
        ];
    }

    protected function getData(): array
    {
        $platformAccountId = $this->getPlatformAccountId();
        $since = $this->filters['since'] ?? '2025-04-05';
        $until = $this->filters['until'] ?? '2025-04-12';

        $analytics = PageAnalytic::where('platform_account_id', $platformAccountId)
            ->whereBetween('date', [$since, $until])
            ->orderBy('date', 'asc')
            ->get();

        $labels = [];
        $followersData = [];
        $impressionsData = [];
        $engagementsData = [];
        $reachData = [];
        $linkClicksData = [];

        $currentDate = Carbon::parse($since);
        $endDate = Carbon::parse($until);

        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->toDateString();
            $labels[] = $dateStr;

            $record = $analytics->firstWhere('date', $dateStr);
            $followersData[] = $record ? $record->followers_count : 0;
            $impressionsData[] = $record ? $record->impressions : 0;
            $engagementsData[] = $record ? $record->engagements : 0;
            $reachData[] = $record ? $record->reach : 0;
            $linkClicksData[] = $record ? $record->link_clicks : 0;

            $currentDate->addDay();
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Số người theo dõi',
                    'data' => $followersData,
                    'borderColor' => 'rgba(75, 192, 192, 1)',
                    'fill' => false,
                ],
                [
                    'label' => 'Lượt xem trang',
                    'data' => $impressionsData,
                    'borderColor' => 'rgba(255, 99, 132, 1)',
                    'fill' => false,
                ],
                [
                    'label' => 'Người dùng tương tác',
                    'data' => $engagementsData,
                    'borderColor' => 'rgba(54, 162, 235, 1)',
                    'fill' => false,
                ],
                [
                    'label' => 'Lượt tiếp cận',
                    'data' => $reachData,
                    'borderColor' => 'rgba(255, 206, 86, 1)',
                    'fill' => false,
                ],
                [
                    'label' => 'Lượt nhấp liên kết',
                    'data' => $linkClicksData,
                    'borderColor' => 'rgba(153, 102, 255, 1)',
                    'fill' => false,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}