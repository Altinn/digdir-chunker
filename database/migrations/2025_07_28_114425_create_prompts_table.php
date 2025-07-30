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
        Schema::create('prompts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('llm_provider')->nullable()->default(config('prism.completions_provider'));
            $table->string('llm_model')->nullable()->default(config('prism.completions_model')) ;
            $table->text('content');
            $table->string('type')->index();
            $table->integer('version')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prompts');
    }
};
