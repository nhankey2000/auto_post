<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRepeatSchedulesToAiPostPromptsTable extends Migration
{
    public function up(): void
    {
        Schema::table('ai_post_prompts', function (Blueprint $table) {
            $table->json('repeat_schedules')->nullable()->after('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('ai_post_prompts', function (Blueprint $table) {
            $table->dropColumn('repeat_schedules');
        });
    }
}