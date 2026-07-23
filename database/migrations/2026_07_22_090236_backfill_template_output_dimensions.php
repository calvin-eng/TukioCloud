<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->integer('output_width')->default(1080)->change();
            $table->integer('output_height')->default(1350)->change();
        });

        \DB::table('templates')->whereNull('output_width')->update(['output_width' => 1080]);
        \DB::table('templates')->whereNull('output_height')->update(['output_height' => 1350]);
    }

    public function down(): void
    {
        // not reversing defaults
    }
};
