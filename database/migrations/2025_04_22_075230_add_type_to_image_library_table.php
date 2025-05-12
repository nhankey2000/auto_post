<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTypeToImageLibraryTable extends Migration
{
    public function up()
    {
        Schema::table('image_library', function (Blueprint $table) {
            $table->string('type')->default('image')->after('item'); // Thêm cột type, mặc định là 'image'
        });

        // Cập nhật các bản ghi hiện có thành type = 'image'
        \App\Models\ImageLibrary::whereNull('type')->update(['type' => 'image']);
    }

    public function down()
    {
        Schema::table('image_library', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
}