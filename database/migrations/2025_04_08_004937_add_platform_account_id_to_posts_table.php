<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPlatformAccountIdToPostsTable extends Migration
{
    public function up()
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->unsignedBigInteger('platform_account_id')->nullable()->after('facebook_post_id');
            $table->foreign('platform_account_id')->references('id')->on('platform_accounts')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropForeign(['platform_account_id']);
            $table->dropColumn('platform_account_id');
        });
    }
}