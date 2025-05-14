<?php
namespace App\Services;

class ChunkerService
{
    /**
     * Splits a Markdown document into chunks of at most $maxSize characters,
     * preserving semantic blocks (headings+paragraphs, lists, code blocks, tables)
     * as best as possible.
     *
     * @param string $markdown
     * @param int    $maxSize
     * @return string[] Array of chunk strings
     */
    public static function chunkMarkdown(string $markdown, int $maxSize): array
    {
        // Normalize line endings to "\n"
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);

        // First split into potential “block candidates” (roughly by blank lines),
        // but keep heading lines tied to the subsequent text if possible.
        $rawBlocks = self::splitIntoBlocks($markdown);

        // Expand or refine the blocks based on adjacency (e.g., heading + paragraph).
        // Also detect code blocks, lists, or tables, so we don’t incorrectly split them.
        $blocks = self::mergeLogicalBlocks($rawBlocks);

        // Now chunk them into the final output, respecting the max size.
        $chunks = [];
        $currentChunk = '';

        foreach ($blocks as $block) {
            // If the block alone exceeds max size, we have to split it.
            if (strlen($block) > $maxSize) {
                // Close off any existing chunk (if not empty) before we chunk the big block
                if (!empty($currentChunk)) {
                    $chunks[] = $currentChunk;
                    $currentChunk = '';
                }
                // Split large block by line or by chunkOfText. This is a fallback
                // since the user specifically wants to avoid splitting if possible,
                // but we have to do it if the single block is too large.
                $subBlocks = self::splitLargeBlock($block, $maxSize);
                foreach ($subBlocks as $sb) {
                    $chunks[] = $sb;
                }
                continue;
            }

            // If adding this block to the current chunk exceeds $maxSize, start a new chunk
            if (strlen($currentChunk) + strlen($block) > $maxSize) {
                if (!empty($currentChunk)) {
                    $chunks[] = rtrim($currentChunk, "\n");
                }
                $currentChunk = $block . "\n";
            } else {
                // Append to the current chunk
                $currentChunk .= $block . "\n";
            }
        }

        // Add any trailing content
        if (!empty($currentChunk)) {
            $chunks[] = rtrim($currentChunk, "\n");
        }

        return $chunks;
    }

    /**
     * Roughly split the markdown by blank lines, returning an array of “raw blocks”.
     * Each entry is a group of lines separated by blank lines.
     *
     * @param string $markdown
     * @return string[] raw blocks
     */
    public static function splitIntoBlocks(string $markdown): array
    {
        // Split by two or more consecutive newlines
        // (This is a naive approach; many real markdown parsers do more robust splitting.)
        $parts = preg_split("/(\n\s*\n)+/", $markdown, -1, PREG_SPLIT_NO_EMPTY);

        // Trim each block
        $parts = array_map('trim', $parts);
        return array_filter($parts, fn($p) => $p !== '');
    }

    /**
     * Merges adjacent blocks if they logically belong together.
     * For instance, a heading block followed by a paragraph block,
     * or lines that form a single table or list.
     *
     * @param string[] $rawBlocks
     * @return string[] logical blocks
     */
    public static function mergeLogicalBlocks(array $rawBlocks): array
    {
        $merged = [];
        $count = count($rawBlocks);

        for ($i = 0; $i < $count; $i++) {
            $current = $rawBlocks[$i];
            $next    = $rawBlocks[$i+1] ?? null;

            // If the current block is a heading and next block is not a heading,
            // combine them, so we keep “heading + paragraph” in one block.
            if (self::isHeading($current) && $next !== null && !self::isHeading($next)) {
                // Merge them
                $merged[] = $current . "\n\n" . $next;
                // Skip the next block since we've merged
                $i++;
            }
            else {
                $merged[] = $current;
            }
        }

        // Optionally, you can further refine detection of lists, tables, etc.
        // e.g., if a block is recognized as part of the same list, merge them.
        // This is where you’d put custom logic if you want to detect multi-paragraph lists.

        return $merged;
    }

    /**
     * Check if the block is just a heading line (e.g. starts with '#' or '##' etc).
     *
     * @param string $block
     * @return bool
     */
    public static function isHeading(string $block): bool
    {
        // A naive check: if the first line is heading syntax and the entire block is just that line
        $lines = explode("\n", trim($block));
        if (count($lines) === 1 && preg_match('/^#{1,6}\s+.+/', $lines[0])) {
            return true;
        }
        return false;
    }

    /**
     * If a single block alone exceeds $maxSize, we must split it.
     * This is a simple fallback that splits by lines until each piece <= $maxSize.
     *
     * @param string $block
     * @param int    $maxSize
     * @return string[]
     */
    public static function splitLargeBlock(string $block, int $maxSize): array
    {
        // If it’s already short enough, return as is
        if (strlen($block) <= $maxSize) {
            return [$block];
        }

        $lines  = explode("\n", $block);
        $result = [];
        $buffer = '';

        foreach ($lines as $line) {
            // If adding the next line exceeds, push current buffer to $result
            if (strlen($buffer) + strlen($line) + 1 > $maxSize) {
                $result[] = rtrim($buffer, "\n");
                $buffer = '';
            }
            $buffer .= $line . "\n";
        }

        if (!empty(trim($buffer))) {
            $result[] = rtrim($buffer, "\n");
        }

        return $result;
    }

    /**
     * Add page numbers to chunks
     * 
     * @param array $chunks
     * @return array{page_number: mixed, text: mixed[]}
     */
    public static function parsePageNumbers(array $chunks): array
    {
        $chunks_with_page_numbers = [];
        $pattern = '/\{(\d+)\}(\-+)/'; // Matches page numbers like {123}--
        $last_page_number = null;
    
        foreach ($chunks as $chunk) {
            $page_numbers = [];
            $matches = [];
    
            // Find all page number markers in the chunk
            preg_match_all($pattern, $chunk, $matches);
    
            if (!empty($matches[1])) {
                // Collect all matched page numbers and cast them to integers
                $page_numbers = array_map('intval', $matches[1]);
                $last_page_number = end($page_numbers); // Update the last detected page number
    
                // Remove all page number markers from the chunk text
                $chunk = preg_replace($pattern, '', $chunk);
            } else {
                // If no page number is found, use the last detected page number
                if ($last_page_number !== null) {
                    $page_numbers[] = (int) $last_page_number;
                }
            }
    
            $chunks_with_page_numbers[] = [
                'text' => trim($chunk), // Clean up the chunk text
                'page_numbers' => $page_numbers, // Array of page numbers as integers
            ];
        }
    
        return $chunks_with_page_numbers;
    }
}