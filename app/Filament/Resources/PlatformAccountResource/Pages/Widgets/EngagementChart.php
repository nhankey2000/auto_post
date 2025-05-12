<?php

namespace App\Filament\Resources\PlatformAccountResource\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\PageAnalytic;
use Carbon\Carbon;

class EngagementChart extends ChartWidget
{
    protected static ?string $heading = 'Tương Tác Hàng Ngày (30 Ngày Qua)';
    public $platformAccount;

    protected function getData(): array
    {
        $analytics = PageAnalytic::where('platform_account_id', $this->platformAccount->id)
            ->whereBetween('date', [
                Carbon::today()->subDays(30),
                Carbon::today(),
            ])
            ->orderBy('date')
            ->get();

        $labels = $analytics->pluck('date')->map(fn($date) => $date->format('d/m'));
        $data = $analytics->pluck('engagements');

        return [
            'datasets' => [
                [
                    'label' => 'Số Lượt Tương Tác',
                    'data' => $data,
                    'backgroundColor' => '#FF9800',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}