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
        Schema::table('files', function (Blueprint $table) {
            $table->text('title')->nullable();
            $table->text('subtitle')->nullable();
            $table->string('type')->nullable();
            $table->json('authors')->nullable();
            $table->json('owners')->nullable();
            $table->json('recipients')->nullable();
            $table->json('publishers')->nullable();
            $table->json('authoring_actors')->nullable();
            $table->date('published_date')->nullable();
            $table->date('authored_date')->nullable();
            $table->string('isbn')->nullable();
            $table->string('issn')->nullable();
            $table->string('document_id')->nullable();
            $table->string('kudos_id')->nullable();
            $table->integer('concerned_year')->nullable();
            $table->text('source_document_url')->nullable();
            $table->text('source_page_url')->nullable();
            $table->datetime('metadata_analyzed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn([
                'title',
                'subtitle',
                'type',
                'authors',
                'owners',
                'recipients',
                'publishers',
                'authoring_actors',
                'published_date',
                'authored_date',
                'isbn',
                'issn',
                'document_id',
                'kudos_id',
                'concerned_year',
                'source_document_url',
                'source_page_url',
                'metadata_analyzed_at',
            ]);
        });
    }
};
