<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddItemColumnToImageLibraryTable extends Migration
{
    public function up(): void
    {
        Schema::table('image_library', function (Blueprint $table) {
            $table->string('item')->nullable()->after('category_id'); // Thêm cột item để lưu đường dẫn ảnh
        });
    }

    public function down(): void
    {
        Schema::table('image_library', function (Blueprint $table) {
            $table->dropColumn('item'); // Xóa cột item nếu rollback
        });
    }
}