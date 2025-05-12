<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('post_reposts', function (Blueprint $table) {
            // Xóa cột platform_account_id
            $table->dropForeign(['platform_account_id']);
            $table->dropColumn('platform_account_id');
            // Thêm cột platform_account_ids
            $table->json('platform_account_ids');
        });
    }

    public function down(): void {
        Schema::table('post_reposts', function (Blueprint $table) {
            // Khôi phục cột platform_account_id
            $table->foreignId('platform_account_id')->constrained('platform_accounts')->onDelete('cascade');
            // Xóa cột platform_account_ids
            $table->dropColumn('platform_account_ids');
        });
    }
};