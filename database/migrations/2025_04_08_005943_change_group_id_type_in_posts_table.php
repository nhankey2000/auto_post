<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeGroupIdTypeInPostsTable extends Migration
{
    public function up()
    {
        Schema::table('posts', function (Blueprint $table) {
            // Thay đổi kiểu dữ liệu của cột group_id thành string (hoặc uuid)
            $table->string('group_id', 36)->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('posts', function (Blueprint $table) {
            // Đổi lại thành unsignedBigInteger nếu cần rollback
            $table->unsignedBigInteger('group_id')->nullable()->change();
        });
    }
}