<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddImageCountToAiPostPromptsTable extends Migration
{
    public function up(): void
    {
        Schema::table('ai_post_prompts', function (Blueprint $table) {
            $table->integer('image_count')->nullable(); // Không cần chỉ định vị trí

        });
    }

    public function down(): void
    {
        Schema::table('ai_post_prompts', function (Blueprint $table) {
            $table->dropColumn('image_count');
        });
    }
}