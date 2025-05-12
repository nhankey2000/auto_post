<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_post_prompts', function (Blueprint $table) {
            $table->string('post_option')->nullable()->after('platform_id'); // "all" hoặc "selected"
            $table->json('selected_pages')->nullable()->after('post_option'); // Lưu danh sách ID của các trang được chọn
        });
    }

    public function down(): void
    {
        Schema::table('ai_post_prompts', function (Blueprint $table) {
            $table->dropColumn('post_option');
            $table->dropColumn('selected_pages');
        });
    }
};