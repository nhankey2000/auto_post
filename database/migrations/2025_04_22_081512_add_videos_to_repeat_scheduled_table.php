<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVideosToRepeatScheduledTable extends Migration
{
    public function up()
    {
        Schema::table('repeat_scheduled', function (Blueprint $table) {
            $table->json('videos')->nullable()->after('images'); // Thêm cột videos
        });
    }

    public function down()
    {
        Schema::table('repeat_scheduled', function (Blueprint $table) {
            $table->dropColumn('videos');
        });
    }
}