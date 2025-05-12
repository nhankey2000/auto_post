<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePageAccessTokensTable extends Migration
{
    public function up()
    {
        Schema::create('page_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('platform_account_id'); // ID của trang (liên kết với bảng platform_accounts)
            $table->string('page_id'); // ID của trang trên Facebook
            $table->string('page_name'); // Tên trang
            $table->text('access_token'); // Access token của trang
            $table->timestamps();

            // Foreign key liên kết với bảng platform_accounts
            $table->foreign('platform_account_id')->references('id')->on('platform_accounts')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('page_access_tokens');
    }
}