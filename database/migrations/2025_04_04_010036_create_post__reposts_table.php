<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('post_reposts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->onDelete('cascade'); // Bài viết gốc
            $table->foreignId('platform_account_id')->constrained('platform_accounts')->onDelete('cascade');
            $table->timestamp('reposted_at')->nullable(); // Thời gian đăng lại
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('post_reposts');
    }
};
