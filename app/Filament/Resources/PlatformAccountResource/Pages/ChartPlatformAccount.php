<?php

namespace App\Filament\Resources\PlatformAccountResource\Pages;

use App\Filament\Resources\PlatformAccountResource;
use App\Filament\Widgets\AnalyticsChart;
use Filament\Resources\Pages\Page;

class ChartPlatformAccount extends Page
{
    protected static string $resource = PlatformAccountResource::class;

    protected static ?string $title = 'Biểu đồ tăng trưởng';

    protected static string $view = 'filament.resources.platform-account-resource.pages.chart-platform-account'; // Thêm thuộc tính $view

    protected function getHeaderWidgets(): array
    {
        return [
            AnalyticsChart::class,
        ];
    }
}