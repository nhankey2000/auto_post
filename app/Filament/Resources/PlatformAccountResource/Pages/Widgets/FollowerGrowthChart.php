<?php

namespace App\Filament\Resources\PlatformAccountResource\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\PageAnalytic;
use Carbon\Carbon;

class FollowerGrowthChart extends ChartWidget
{
    protected static ?string $heading = 'Tăng Trưởng Người Theo Dõi (30 Ngày Qua)';
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
        $data = $analytics->pluck('followers_count');

        return [
            'datasets' => [
                [
                    'label' => 'Số Người Theo Dõi',
                    'data' => $data,
                    'borderColor' => '#4CAF50',
                    'fill' => false,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}