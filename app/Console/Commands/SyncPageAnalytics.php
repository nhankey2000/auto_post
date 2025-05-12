<?php

namespace App\Console\Commands;

use App\Models\PlatformAccount;
use App\Services\FacebookService;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SyncPageAnalytics extends Command
{
    // Câu lệnh Artisan để chạy: php artisan analytics:sync {days}
    protected $signature = 'analytics:sync {days=7 : Số ngày cần đồng bộ dữ liệu (tối đa 90 ngày)}';
    protected $description = 'Đồng bộ dữ liệu thống kê từ Facebook Insights cho tất cả các fanpage';

    protected $facebookService;

    public function __construct(FacebookService $facebookService)
    {
        parent::__construct();
        $this->facebookService = $facebookService;
    }

    public function handle()
    {
        $days = (int) $this->argument('days');

        // Facebook chỉ cho phép lấy dữ liệu trong tối đa 90 ngày
        if ($days > 90) {
            $this->warn("⚠️ Số ngày vượt quá giới hạn 90 ngày. Hệ thống sẽ tự động lấy dữ liệu trong 90 ngày gần nhất.");
            $days = 90;
        }

        $since = Carbon::today()->subDays($days)->toDateString(); // Ngày bắt đầu
        $until = Carbon::today()->toDateString(); // Ngày kết thúc

        // Lọc ra các tài khoản Facebook (platform_id = 1)
        $platformAccounts = PlatformAccount::where('platform_id', 1)->get();

        foreach ($platformAccounts as $platformAccount) {
            // Kiểm tra thông tin cần thiết để gọi API Facebook
            if (
                empty($platformAccount->page_id) ||
                empty($platformAccount->access_token) ||
                empty($platformAccount->app_id) ||
                empty($platformAccount->app_secret)
            ) {
                $this->warn("⛔ Bỏ qua tài khoản '{$platformAccount->name}': Thiếu page_id, access_token, app_id hoặc app_secret");

                Log::warning("Bỏ qua đồng bộ thống kê cho {$platformAccount->name}: Thiếu trường bắt buộc", [
                    'page_id' => $platformAccount->page_id,
                    'access_token' => $platformAccount->access_token ? 'có' : 'thiếu',
                    'app_id' => $platformAccount->app_id,
                    'app_secret' => $platformAccount->app_secret ? 'có' : 'thiếu',
                ]);

                continue;
            }

            try {
                $this->info("🔄 Đang đồng bộ dữ liệu thống kê cho fanpage: {$platformAccount->name}...");
                $this->facebookService->storePageAnalytics($platformAccount, $since, $until);
                $this->info("✅ Đồng bộ thành công dữ liệu cho: {$platformAccount->name}");
            } catch (\Exception $e) {
                $this->error("❌ Lỗi khi đồng bộ dữ liệu cho {$platformAccount->name}: {$e->getMessage()}");

                Log::error("Lỗi khi đồng bộ dữ liệu cho {$platformAccount->name}", [
                    'error' => $e->getMessage(),
                    'page_id' => $platformAccount->page_id,
                    'since' => $since,
                    'until' => $until,
                ]);
            }
        }
    }
}
