<?php

namespace App\Jobs;

use App\Models\File;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;

class AnalyzeMetadata implements ShouldQueue
{
    use Queueable;

    protected File $file;

    /**
     * Create a new job instance.
     */
    public function __construct(File $file)
    {
        $this->file = $file;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Get the first few chunks that might contain metadata
        $chunks = $this->file->chunks()
            ->orderBy('chunk_number')
            ->limit(10) // Process up to 10 chunks to find metadata
            ->get();

        if ($chunks->isEmpty()) {
            Log::warning("No chunks available for metadata analysis: File ID {$this->file->id}");

            return;
        }

        // Combine text from chunks until we have enough content or hit table of contents
        $combinedText = '';
        $processedChunks = 0;

        foreach ($chunks as $chunk) {
            $combinedText .= $chunk->text."\n\n";
            $processedChunks++;

            // Stop if we encounter table of contents or similar markers
            if (preg_match('/\b(table\s+of\s+contents|contents|index)\b/i', $chunk->text)) {
                break;
            }

            // Stop if we have enough text (around 3000 characters should be sufficient)
            if (strlen($combinedText) > 3000) {
                break;
            }
        }

        Log::info("Processing {$processedChunks} chunks for metadata analysis: File ID {$this->file->id}");

        // Extract metadata using Prism
        $metadata = $this->extractMetadata($combinedText);

        // Update file with extracted metadata in specific fields
        $this->file->title = $metadata['title'];
        $this->file->authors = $metadata['authors'];
        $this->file->published_date = $metadata['published_date'];
        $this->file->authored_date = $metadata['authored_date'];
        $this->file->isbn = $metadata['isbn'];
        $this->file->document_id = $metadata['document_id'];
        $this->file->summary = $metadata['summary'];
        $this->file->metadata_analyzed_at = Carbon::now();

        // Also update the metadata JSON field for backward compatibility
        $existingMetadata = $this->file->metadata ?? [];
        $this->file->metadata = array_merge($existingMetadata, [
            'chunks_analyzed' => $processedChunks,
        ]);

        $this->file->save();

        Log::info("Metadata analysis completed for File ID {$this->file->id}");
    }

    /**
     * Extract metadata from text using Prism
     */
    private function extractMetadata(string $text): array
    {
        $provider = config('prism.completions_provider');
        $model = config('prism.completions_model');

        if (! $provider || ! $model) {
            Log::warning('Prism configuration missing - using regex fallback for metadata extraction');

            return $this->extractMetadataWithRegex($text);
        }

        $prompt = 'Analyze the following document text and extract metadata. Return ONLY a valid JSON object with these fields (use null for missing values):
{
  "title": "document title",
  "authors": ["author1", "author2"],
  "published_date": "YYYY-MM-DD or null",
  "authored_date": "YYYY-MM-DD or null", 
  "isbn": "ISBN number or null",
  "document_id": "any document ID found or null",
  "summary": "brief summary or null"
}

Document text:
'.substr($text, 0, 4000); // Limit text to avoid token limits

        try {
            $response = Prism::text()
                ->using(Provider::{$provider}, $model)
                ->withPrompt($prompt)
                ->asText();

            $responseText = trim($response->text, "`json \n\r");

            // Try to parse the JSON response
            $metadata = json_decode($responseText, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($metadata)) {
                return $this->sanitizeMetadata($metadata);
            }

            Log::warning('Prism metadata extraction failed, using regex fallback. Response: '.$responseText);

        } catch (\Exception $e) {
            Log::error('Error calling Prism for metadata extraction: '.$e->getMessage());
        }

        // Fallback to regex-based extraction
        return $this->extractMetadataWithRegex($text);
    }

    /**
     * Fallback regex-based metadata extraction
     */
    private function extractMetadataWithRegex(string $text): array
    {
        $metadata = [
            'title' => null,
            'authors' => null,
            'published_date' => null,
            'authored_date' => null,
            'isbn' => null,
            'document_id' => null,
            'summary' => null,
        ];

        // Extract title (usually the first large heading or bold text)
        if (preg_match('/^#\s+(.+)$/m', $text, $matches)) {
            $metadata['title'] = trim($matches[1]);
        } elseif (preg_match('/\*\*(.{10,100}?)\*\*/', $text, $matches)) {
            $metadata['title'] = trim($matches[1]);
        }

        // Extract authors (common patterns)
        if (preg_match('/(?:by|author[s]?:|written\s+by)\s*:?\s*([^.\n]+)/i', $text, $matches)) {
            $authors = preg_split('/[,&]|\sand\s/', $matches[1]);
            $metadata['authors'] = array_map('trim', $authors);
        }

        // Extract ISBN
        if (preg_match('/isbn[:\s]*([0-9\-x]{10,17})/i', $text, $matches)) {
            $metadata['isbn'] = trim($matches[1]);
        }

        // Extract dates (various formats)
        if (preg_match('/(?:published|publication|date)[:\s]*([0-9]{4}[-\/][0-9]{1,2}[-\/][0-9]{1,2})/i', $text, $matches)) {
            $metadata['published_date'] = $this->normalizeDate($matches[1]);
        } elseif (preg_match('/([0-9]{4})/', $text, $matches)) {
            $year = (int) $matches[1];
            if ($year >= 1900 && $year <= date('Y')) {
                $metadata['published_date'] = $matches[1].'-01-01';
            }
        }

        return $metadata;
    }

    /**
     * Sanitize and validate extracted metadata
     */
    private function sanitizeMetadata(array $metadata): array
    {
        $clean = [
            'title' => null,
            'authors' => null,
            'published_date' => null,
            'authored_date' => null,
            'isbn' => null,
            'document_id' => null,
            'summary' => null,
        ];

        // Title
        if (! empty($metadata['title']) && is_string($metadata['title'])) {
            $clean['title'] = trim($metadata['title']);
        }

        // Authors
        if (! empty($metadata['authors'])) {
            if (is_array($metadata['authors'])) {
                $clean['authors'] = array_filter(array_map('trim', $metadata['authors']));
            } elseif (is_string($metadata['authors'])) {
                $clean['authors'] = [trim($metadata['authors'])];
            }
        }

        // Dates
        foreach (['published_date', 'authored_date'] as $dateField) {
            if (! empty($metadata[$dateField]) && is_string($metadata[$dateField])) {
                $clean[$dateField] = $this->normalizeDate($metadata[$dateField]);
            }
        }

        // ISBN
        if (! empty($metadata['isbn']) && is_string($metadata['isbn'])) {
            $clean['isbn'] = trim($metadata['isbn']);
        }

        // Document ID
        if (! empty($metadata['document_id']) && is_string($metadata['document_id'])) {
            $clean['document_id'] = trim($metadata['document_id']);
        }

        // Summary
        if (! empty($metadata['summary']) && is_string($metadata['summary'])) {
            $clean['summary'] = trim($metadata['summary']);
        }

        return $clean;
    }

    /**
     * Normalize date to YYYY-MM-DD format
     */
    private function normalizeDate(string $date): ?string
    {
        try {
            $dateTime = new \DateTime($date);

            return $dateTime->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("AnalyzeMetadata job failed for file ID {$this->file->id}: ".$exception->getMessage());
    }
}
