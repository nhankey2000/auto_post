<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('post_reposts', function (Blueprint $table) {
            $table->string('facebook_post_id')->nullable()->after('reposted_at');
        });
    }

    public function down(): void {
        Schema::table('post_reposts', function (Blueprint $table) {
            $table->dropColumn('facebook_post_id');
        });
    }
};