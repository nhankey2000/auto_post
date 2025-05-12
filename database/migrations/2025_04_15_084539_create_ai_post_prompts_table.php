<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAiPostPromptsTable extends Migration
{
    public function up()
    {
        Schema::create('ai_post_prompts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained('platforms')->cascadeOnDelete();
            $table->text('prompt'); // yêu cầu đầu vào
          
            $table->timestamp('scheduled_at')->nullable(); // thời điểm lên lịch
            $table->enum('status', ['pending', 'generating', 'generated', 'posted'])->default('pending');

            $table->longText('generated_content')->nullable(); // nội dung GPT sinh ra
          

            $table->timestamp('posted_at')->nullable(); // thời điểm đăng thành công
            $table->timestamps(); // created_at & updated_at
        });
    }

    public function down()
    {
        Schema::dropIfExists('ai_post_prompts');
    }
}
