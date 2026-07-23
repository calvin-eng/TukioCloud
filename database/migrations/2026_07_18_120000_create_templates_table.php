<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('background_path')->nullable();
            $table->integer('name_x')->default(540);
            $table->integer('name_y')->default(600);
            $table->integer('qr_x')->default(390);
            $table->integer('qr_y')->default(950);
            $table->integer('qr_size')->default(300);
            $table->string('name_font_color')->default('#1a1a1a');
            $table->integer('name_font_size')->default(48);
            $table->string('name_font_path')->nullable();
            $table->integer('output_width')->default(1080);
            $table->integer('output_height')->default(1350);
            $table->integer('output_quality')->default(90);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
