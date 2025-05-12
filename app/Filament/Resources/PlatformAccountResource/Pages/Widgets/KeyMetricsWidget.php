<?php

namespace App\Filament\Resources\PlatformAccountResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\PageAnalytic;
use Carbon\Carbon;

class KeyMetricsWidget extends BaseWidget
{
    public $platformAccount;

    public function record($record): static
    {
        $this->platformAccount = $record;
        return $this;
    }

    protected function getStats(): array
    {
        $latestAnalytic = PageAnalytic::where('platform_account_id', $this->platformAccount->id)
            ->orderBy('date', 'desc')
            ->first();

        $last30Days = PageAnalytic::where('platform_account_id', $this->platformAccount->id)
            ->whereBetween('date', [
                Carbon::today()->subDays(30),
                Carbon::today(),
            ])
            ->get();

        $totalReach = $last30Days->sum('reach');
        $totalEngagements = $last30Days->sum('engagements');
        $engagementRate = $totalReach > 0 ? ($totalEngagements / $totalReach) * 100 : 0;

        return [
            Stat::make('Tổng Số Người Theo Dõi', $latestAnalytic->followers_count ?? 0)
                ->description('Số liệu mới nhất')
                ->color('success'),
            Stat::make('Lượt Tiếp Cận (30 ngày)', number_format($totalReach))
                ->description('Tổng lượt tiếp cận trong 30 ngày qua')
                ->color('primary'),
            Stat::make('Tỷ Lệ Tương Tác (30 ngày)', number_format($engagementRate, 2) . '%')
                ->description('Tương tác / Lượt tiếp cận')
                ->color('warning'),
        ];
    }
}
