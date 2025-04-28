<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->nullable();
            $table->string('type')->default('paragraph')->nullable();
            $table->text('text')->nullable();
            $table->smallInteger('chunk_number')->nullable();
            $table->smallInteger('page_number')->nullable();
            $table->timestamps();
        });

        // DB::statement('ALTER TABLE `chunks` ADD VECTOR INDEX `chunks_embedding_index` (`embedding`) M=8 DISTANCE=cosine');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chunks');
    }
};
