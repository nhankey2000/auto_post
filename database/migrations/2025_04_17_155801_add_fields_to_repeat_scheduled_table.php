<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldsToRepeatScheduledTable extends Migration
{
    public function up()
    {
        Schema::table('repeat_scheduled', function (Blueprint $table) {
            $table->string('title')->nullable()->after('schedule'); // Thêm cột title, có thể null
            $table->text('content')->nullable()->after('title'); // Thêm cột content, có thể null
            $table->json('images')->nullable()->after('content'); // Thêm cột images dạng JSON để lưu danh sách ảnh
        });
    }

    public function down()
    {
        Schema::table('repeat_scheduled', function (Blueprint $table) {
            $table->dropColumn(['title', 'content', 'images']);
        });
    }
}