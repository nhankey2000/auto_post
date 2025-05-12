<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // Tiêu đề bài viết
            $table->text('content'); // Nội dung bài viết
            $table->json('media')->nullable(); // Ảnh/Video đính kèm
            $table->json('hashtags')->nullable(); // Danh sách hashtag
            $table->enum('status', ['draft', 'published', 'scheduled'])->default('draft'); // Trạng thái bài viết
            $table->timestamp('scheduled_at')->nullable(); // Thời gian đăng bài nếu đặt lịch
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('posts');
    }
};