<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use App\Models\PlatformAccount;
use Carbon\Carbon;
use App\Models\PageAnalytic;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;

class GrowthChart extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.pages.growth-chart';

    protected static ?string $navigationLabel = 'Biểu đồ tăng trưởng';

    protected static ?string $title = 'Biểu đồ tăng trưởng';

    protected static ?string $slug = 'growth-chart';

    public ?array $filters = [];

    protected function getFormStatePath(): ?string
    {
        return 'filters';
    }

    public function mount(): void
    {
        $this->filters = [
            'range' => '7',
            'since' => Carbon::today()->subDays(7)->toDateString(),
            'until' => Carbon::today()->toDateString(),
        ];
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('platform_account_id')
                ->label('Chọn tài khoản')
                ->options(PlatformAccount::pluck('name', 'id'))
                ->required()
                ->reactive()
                ->afterStateUpdated(function ($state) {
                    $this->filters['platform_account_id'] = $state;
                    $this->updateChart();
                }),
            Forms\Components\Select::make('range')
                ->label('Chọn khoảng thời gian')
                ->options([
                    '7' => '7 ngày gần nhất',
                    '30' => '30 ngày gần nhất',
                    '60' => '60 ngày gần nhất',
                    '90' => '90 ngày gần nhất',
                    'custom' => 'Tùy chỉnh',
                ])
                ->default('7')
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set) {
                    $this->filters['range'] = $state;
                    if ($state !== 'custom') {
                        $until = Carbon::today();
                        $since = $until->copy()->subDays((int) $state);
                        $set('since', $since->toDateString());
                        $set('until', $until->toDateString());
                        $this->filters['since'] = $since->toDateString();
                        $this->filters['until'] = $until->toDateString();
                    }
                    $this->updateChart();
                }),
            Forms\Components\DatePicker::make('since')
                ->label('Từ ngày')
                ->default(Carbon::today()->subDays(7))
                ->visible(fn ($get) => $get('range') === 'custom')
                ->reactive()
                ->afterStateUpdated(function ($state) {
                    $this->filters['since'] = $state;
                    $this->updateChart();
                }),
            Forms\Components\DatePicker::make('until')
                ->label('Đến ngày')
                ->default(Carbon::today())
                ->visible(fn ($get) => $get('range') === 'custom')
                ->reactive()
                ->afterStateUpdated(function ($state) {
                    $this->filters['until'] = $state;
                    $this->updateChart();
                }),
        ];
    }

    public function updateChart()
    {
        $this->dispatch('update-chart', $this->getChartData());
    }

    public function getChartData(): array
    {
        $platformAccountId = $this->filters['platform_account_id'] ?? null;
        $range = $this->filters['range'] ?? '7';
    
        if ($range === 'custom') {
            $since = $this->filters['since'] ?? Carbon::today()->subDays(7)->toDateString();
            $until = $this->filters['until'] ?? Carbon::today()->toDateString();
        } else {
            $until = Carbon::today();
            $since = $until->copy()->subDays((int) $range);
            $since = $since->toDateString();
            $until = $until->toDateString();
        }
    
        $query = PageAnalytic::query()
            ->whereBetween('date', [$since, $until]);
    
        if ($platformAccountId) {
            $query->where('platform_account_id', $platformAccountId);
        }
    
        $analytics = $query->get();
    
        if ($analytics->isEmpty()) {
            \Filament\Notifications\Notification::make()
                ->title('Không có dữ liệu')
                ->body('Không có dữ liệu trong khoảng thời gian được chọn.')
                ->warning()
                ->send();
        }
    
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
    
            if ($platformAccountId) {
                $record = $analytics->firstWhere('date', $dateStr);
                $followersData[] = $record ? $record->followers_count : 0;
                $impressionsData[] = $record ? $record->impressions : 0;
                $engagementsData[] = $record ? $record->engagements : 0;
                $reachData[] = $record ? $record->reach : 0;
                $linkClicksData[] = $record ? $record->link_clicks : 0;
            } else {
                $dailyAnalytics = $analytics->where('date', $dateStr);
                $followersData[] = $dailyAnalytics->sum('followers_count');
                $impressionsData[] = $dailyAnalytics->sum('impressions');
                $engagementsData[] = $dailyAnalytics->sum('engagements');
                $reachData[] = $dailyAnalytics->sum('reach');
                $linkClicksData[] = $dailyAnalytics->sum('link_clicks');
            }
    
            $currentDate->addDay();
        }
    
        // Thêm log để debug dữ liệu
        \Illuminate\Support\Facades\Log::info('Chart Data:', [
            'labels' => $labels,
            'followersData' => $followersData,
            'impressionsData' => $impressionsData,
            'engagementsData' => $engagementsData,
            'reachData' => $reachData,
            'linkClicksData' => $linkClicksData,
        ]);
    
        $minFollowers = min($followersData) > 0 ? min($followersData) - 10 : 0;
        $maxFollowers = max($followersData) > 0 ? max($followersData) + 10 : 100;
    
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
            'options' => [
                'scales' => [
                    'y' => [
                        'beginAtZero' => false,
                        'min' => $minFollowers,
                        'max' => $maxFollowers,
                        'ticks' => [
                            'stepSize' => 5,
                        ],
                    ],
                ],
            ],
        ];
    }
    public function getSubheading(): ?string
    {
        $range = $this->filters['range'] ?? '7';
        if ($range === 'custom') {
            $since = $this->filters['since'] ?? Carbon::today()->subDays(7)->toDateString();
            $until = $this->filters['until'] ?? Carbon::today()->toDateString();
        } else {
            $until = Carbon::today();
            $since = $until->copy()->subDays((int) $range);
            $since = $since->toDateString();
            $until = $until->toDateString();
        }

        $platformAccountId = $this->filters['platform_account_id'] ?? null;
        $platformAccount = $platformAccountId ? PlatformAccount::find($platformAccountId) : null;

        return $platformAccount
            ? "Tài khoản: {$platformAccount->name} | Dữ liệu từ {$since} đến {$until}"
            : "Vui lòng chọn tài khoản để xem biểu đồ";
    }
    
}