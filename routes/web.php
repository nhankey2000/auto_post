<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\FacebookController;
Route::get('/', function () {
    return view('welcome');
});


// Route để lấy dữ liệu biểu đồ
Route::get('/analytics/growth-chart-data', [AnalyticsController::class, 'getGrowthChartData'])->name('analytics.growth-chart-data');

// Route để hiển thị trang chứa biểu đồ
Route::get('/admin/analytics/growth', function () {
    return view('admin.analytics.growth');
})->name('admin.analytics.growth');


Route::get('/facebook/redirect', [FacebookController::class, 'redirectToFacebook'])->name('facebook.redirect');
Route::get('/facebook/callback', [FacebookController::class, 'handleFacebookCallback'])->name('facebook.callback');