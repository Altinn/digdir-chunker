<?php

namespace Tests\Unit\Jobs;

use App\Jobs\AnalyzeMetadata;
use App\Models\Chunk;
use App\Models\File;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyzeMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_analyze_metadata_job_processes_chunks_successfully()
    {
        // Create a task and file
        $task = Task::factory()->create();
        $file = File::factory()->create(['task_id' => $task->id]);

        // Create test chunks with sample content
        Chunk::create([
            'file_id' => $file->id,
            'chunk_number' => 0,
            'text' => "# Sample Document Title\n\nBy John Doe and Jane Smith\n\nPublished: 2023-05-15\nISBN: 978-1234567890\n\nThis is a sample document about testing metadata extraction.",
            'chunk_type' => 'paragraph',
            'page_numbers' => [1],
        ]);

        Chunk::create([
            'file_id' => $file->id,
            'chunk_number' => 1,
            'text' => "## Introduction\n\nThis document demonstrates how metadata can be extracted from document chunks.\n\nTable of Contents",
            'chunk_type' => 'paragraph',
            'page_numbers' => [1, 2],
        ]);

        // Run the job
        $job = new AnalyzeMetadata($file);
        $job->handle();

        // Refresh the file to get updated data
        $file->refresh();

        // Assert that metadata was extracted and saved
        $this->assertNotNull($file->metadata_analyzed_at);
        $this->assertIsArray($file->metadata);
        $this->assertEquals(2, $file->metadata['chunks_analyzed']);

        // Note: Since we don't have Prism configured in tests, it should fall back to regex extraction
        // The regex should extract some basic metadata from our sample text
    }

    public function test_analyze_metadata_job_handles_empty_chunks()
    {
        // Create a task and file with no chunks
        $task = Task::factory()->create();
        $file = File::factory()->create(['task_id' => $task->id]);

        // Run the job
        $job = new AnalyzeMetadata($file);
        $job->handle();

        // Refresh the file
        $file->refresh();

        // Should not have updated metadata_analyzed_at since no chunks were available
        $this->assertNull($file->metadata_analyzed_at);
    }

    public function test_analyze_metadata_job_stops_at_table_of_contents()
    {
        // Create a task and file
        $task = Task::factory()->create();
        $file = File::factory()->create(['task_id' => $task->id]);

        // Create multiple chunks, with table of contents in the second one
        for ($i = 0; $i < 5; $i++) {
            Chunk::create([
                'file_id' => $file->id,
                'chunk_number' => $i,
                'text' => $i === 1 ? "Table of Contents\n\n1. Introduction\n2. Methods" : "Chunk {$i} content",
                'chunk_type' => 'paragraph',
                'page_numbers' => [$i + 1],
            ]);
        }

        // Run the job
        $job = new AnalyzeMetadata($file);
        $job->handle();

        // Refresh the file
        $file->refresh();

        // Should have processed only 2 chunks (0 and 1) before stopping at table of contents
        $this->assertEquals(2, $file->metadata['chunks_analyzed']);
    }
}
