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
        Schema::create('chunk_derivatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chunk_id')->constrained()->onDelete('cascade');
            $table->foreignId('prompt_id')->constrained()->onDelete('cascade');
            $table->string('type')->index();
            $table->text('content');
            $table->string('llm_provider')->nullable();
            $table->string('llm_model')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chunk_derivatives');
    }
};
