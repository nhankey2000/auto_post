<?php

namespace App\Console;
use Illuminate\Support\Facades\Log;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->call(function () {
            Log::info('✅ Task schedule chạy OK lúc: ' . now());
        })->everyMinute();
    $schedule->command('posts:auto-post')->everyMinute();
    $schedule->command('prompts:process')->everyMinute();
    $schedule->command('analytics:sync')->everyMinute(); 
  
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}