<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateImageLibraryTable extends Migration
{
    public function up(): void
    {
        Schema::create('image_library', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable(); // Khóa ngoại liên kết với bảng categories
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
            // $table->json('img'); // Lưu danh sách ảnh dưới dạng JSON
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_library');
    }
}