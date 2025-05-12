<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddImageToAiPostPromptsTable extends Migration
{
    public function up()
    {
        Schema::table('ai_post_prompts', function (Blueprint $table) {
            $table->string('image')->nullable()->after('prompt'); // Thêm cột image, nullable
        });
    }

    public function down()
    {
        Schema::table('ai_post_prompts', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
}