<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddImageCategoryToAiPostPromptsTable extends Migration
{
    public function up(): void
    {
        Schema::table('ai_post_prompts', function (Blueprint $table) {
            $table->unsignedBigInteger('image_category')->nullable()->after('prompt');
            $table->foreign('image_category')->references('id')->on('categories')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('ai_post_prompts', function (Blueprint $table) {
            $table->dropForeign(['image_category']);
            $table->dropColumn('image_category');
        });
    }
}