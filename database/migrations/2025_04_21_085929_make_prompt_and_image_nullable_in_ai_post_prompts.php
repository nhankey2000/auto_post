<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_post_prompts', function (Blueprint $table) {
            $table->text('prompt')->nullable()->change();
            $table->string('image')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ai_post_prompts', function (Blueprint $table) {
            $table->text('prompt')->nullable(false)->change();
            $table->string('image')->nullable(false)->change();
        });
    }
};