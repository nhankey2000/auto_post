<?php

namespace App\Http\Controllers;

use App\Models\PageAnalytic;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    public function getGrowthChartData(Request $request)
    {
        // Lấy khoảng thời gian (mặc định từ 2025-04-05 đến 2025-04-12)
        $since = $request->input('since', '2025-04-05');
        $until = $request->input('until', '2025-04-12');

        // Lấy tất cả platform_account_id (1 đến 5)
        $platformAccountIds = PageAnalytic::select('platform_account_id')
            ->distinct()
            ->pluck('platform_account_id')
            ->toArray();

        // Chuẩn bị dữ liệu cho biểu đồ
        $labels = [];
        $datasets = [];

        // Tạo danh sách các ngày từ $since đến $until
        $currentDate = Carbon::parse($since);
        $endDate = Carbon::parse($until);
        while ($currentDate <= $endDate) {
            $labels[] = $currentDate->toDateString();
            $currentDate->addDay();
        }

        // Lấy dữ liệu followers_count cho từng platform_account_id
        foreach ($platformAccountIds as $index => $platformAccountId) {
            $analytics = PageAnalytic::where('platform_account_id', $platformAccountId)
                ->whereBetween('date', [$since, $until])
                ->orderBy('date', 'asc')
                ->get();

            $data = [];
            $currentDate = Carbon::parse($since);
            while ($currentDate <= $endDate) {
                $dateStr = $currentDate->toDateString();
                $record = $analytics->firstWhere('date', $dateStr);
                $data[] = $record ? $record->followers_count : 0;
                $currentDate->addDay();
            }

            // Tạo dataset cho từng platform_account_id
            $colors = [
                'rgba(75, 192, 192, 1)',  // Màu xanh lam
                'rgba(255, 99, 132, 1)',  // Màu đỏ
                'rgba(54, 162, 235, 1)',  // Màu xanh dương
                'rgba(255, 206, 86, 1)',  // Màu vàng
                'rgba(153, 102, 255, 1)', // Màu tím
            ];

            $datasets[] = [
                'label' => "Tài khoản $platformAccountId",
                'data' => $data,
                'borderColor' => $colors[$index % count($colors)],
                'fill' => false,
            ];
        }

        return response()->json([
            'labels' => $labels,
            'datasets' => $datasets,
        ]);
    }
}