<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('platform_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained('platforms')->onDelete('cascade');
            $table->string('name'); // Tên tài khoản
            $table->string('app_id')->nullable(); // App ID (Facebook, Zalo, TikTok, YouTube)
            $table->string('app_secret')->nullable(); // App Secret
            $table->string('access_token')->nullable(); // Access Token
            $table->string('api_key')->nullable(); // API Key
            $table->string('api_secret')->nullable(); // API Secret
            $table->json('extra_data')->nullable(); // Dữ liệu bổ sung
            $table->timestamp('expires_at')->nullable(); // Ngày hết hạn token
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('platform_accounts');
    }
};