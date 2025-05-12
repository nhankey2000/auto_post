<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_account_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->integer('followers_count')->default(0);
            $table->integer('reach')->default(0);
            $table->integer('impressions')->default(0);
            $table->integer('engagements')->default(0);
            $table->integer('link_clicks')->default(0);
            $table->timestamps();

            // Đảm bảo mỗi fanpage chỉ có 1 bản ghi thống kê mỗi ngày
            $table->unique(['platform_account_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_analytics');
    }
};