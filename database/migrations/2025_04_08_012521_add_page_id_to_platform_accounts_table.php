<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPageIdToPlatformAccountsTable extends Migration
{
    public function up()
    {
        Schema::table('platform_accounts', function (Blueprint $table) {
            $table->string('page_id')->nullable()->after('name');
        });
    }

    public function down()
    {
        Schema::table('platform_accounts', function (Blueprint $table) {
            $table->dropColumn('page_id');
        });
    }
}