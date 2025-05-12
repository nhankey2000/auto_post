<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdatePostRepostsTable extends Migration
{
    public function up()
    {
        Schema::table('post_reposts', function (Blueprint $table) {
            // Xóa cột platform_account_ids
            $table->dropColumn('platform_account_ids');
            // Thêm cột platform_account_id
            $table->unsignedBigInteger('platform_account_id')->nullable()->after('post_id');
            $table->foreign('platform_account_id')->references('id')->on('platform_accounts')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('post_reposts', function (Blueprint $table) {
            $table->dropForeign(['platform_account_id']);
            $table->dropColumn('platform_account_id');
            $table->json('platform_account_ids')->nullable()->after('post_id');
        });
    }
}