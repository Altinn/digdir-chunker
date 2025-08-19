<?php

namespace Tests\Unit\Jobs;

use App\Jobs\AnalyzeMetadata;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class AnalyzeMetadataUnitTest extends TestCase
{
    public function test_regex_metadata_extraction()
    {
        $job = new AnalyzeMetadata($this->createMockFile());
        $reflection = new ReflectionClass($job);
        $method = $reflection->getMethod('extractMetadataWithRegex');
        $method->setAccessible(true);

        $sampleText = "# Sample Document Title\n\nBy John Doe and Jane Smith\n\nPublished: 2023-05-15\nISBN: 978-1234567890\n\nThis is a sample document about testing metadata extraction.";

        $result = $method->invoke($job, $sampleText);

        $this->assertEquals('Sample Document Title', $result['title']);
        $this->assertEquals(['John Doe', 'Jane Smith'], $result['authors']);
        $this->assertEquals('2023-05-15', $result['published_date']);
        $this->assertEquals('978-1234567890', $result['isbn']);
    }

    public function test_date_normalization()
    {
        $job = new AnalyzeMetadata($this->createMockFile());
        $reflection = new ReflectionClass($job);
        $method = $reflection->getMethod('normalizeDate');
        $method->setAccessible(true);

        $this->assertEquals('2023-05-15', $method->invoke($job, '2023-05-15'));
        $this->assertEquals('2023-05-15', $method->invoke($job, '2023/05/15'));
        $this->assertNull($method->invoke($job, 'invalid-date'));
    }

    public function test_metadata_sanitization()
    {
        $job = new AnalyzeMetadata($this->createMockFile());
        $reflection = new ReflectionClass($job);
        $method = $reflection->getMethod('sanitizeMetadata');
        $method->setAccessible(true);

        $dirtyMetadata = [
            'title' => '  Sample Title  ',
            'authors' => 'John Doe',
            'published_date' => '2023-05-15',
            'invalid_field' => 'should be ignored',
        ];

        $result = $method->invoke($job, $dirtyMetadata);

        $this->assertEquals('Sample Title', $result['title']);
        $this->assertEquals(['John Doe'], $result['authors']);
        $this->assertEquals('2023-05-15', $result['published_date']);
        $this->assertNull($result['isbn']);
    }

    private function createMockFile()
    {
        return new class
        {
            public $id = 1;
        };
    }
}
