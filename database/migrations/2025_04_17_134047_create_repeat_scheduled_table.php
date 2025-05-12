<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRepeatScheduledTable extends Migration
{
    public function up()
    {
        Schema::create('repeat_scheduled', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ai_post_prompts_id');
          
            $table->string('facebook_post_id')->nullable(); // Lưu ID bài đăng trên Facebook
            $table->timestamp('schedule')->nullable(); // Lưu thời gian dự kiến đăng
            $table->timestamps(); // created_at và updated_at

            // Foreign key constraint
            $table->foreign('ai_post_prompts_id')->references('id')->on('ai_post_prompts')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('repeat_scheduled');
    }
}