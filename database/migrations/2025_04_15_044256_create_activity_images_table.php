<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateActivityImagesTable extends Migration
{
    public function up()
    {
        Schema::create('activity_images', function (Blueprint $table) {
            $table->id();
            $table->date('activity_date');
            $table->string('image_path');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('activity_images');
    }
}
