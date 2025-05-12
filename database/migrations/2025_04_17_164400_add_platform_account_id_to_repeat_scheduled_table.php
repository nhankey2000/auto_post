<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repeat_scheduled', function (Blueprint $table) {
            $table->unsignedBigInteger('platform_account_id')->nullable()->after('facebook_post_id');
        });
    }

    public function down(): void
    {
        Schema::table('repeat_scheduled', function (Blueprint $table) {
            $table->dropColumn('platform_account_id');
        });
    }
};