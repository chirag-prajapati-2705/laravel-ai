<?php

declare(strict_types=1);

namespace App\Services;

/**
 * A recursive character text splitter that prioritizes splitting at natural boundaries
 * like paragraphs, sentences, and words to preserve semantic context in RAG chunks.
 */
class TextSplitter
{
    /**
     * Split text into chunks with a specified size and overlap.
     *
     * @param  string  $text  The text to split.
     * @param  int  $chunkSize  The maximum size of each chunk in characters.
     * @param  int  $chunkOverlap  The number of characters to overlap between chunks.
     * @param  array<int, string>  $delimiters  The delimiters to use for splitting, in order of priority.
     * @return array<int, string>
     */
    public function split(
        string $text,
        int $chunkSize = 1000,
        int $chunkOverlap = 200,
        array $delimiters = ["\n\n", "\n", ' ', '']
    ): array {
        if (trim($text) === '') {
            return [];
        }

        $splits = $this->recursiveSplit($text, $delimiters, $chunkSize);

        return $this->mergeSplits($splits, $chunkSize, $chunkOverlap);
    }

    /**
     * Recursively split text using a list of delimiters.
     *
     * @param  array<int, string>  $delimiters
     * @return array<int, string>
     */
    private function recursiveSplit(string $text, array $delimiters, int $chunkSize): array
    {
        if (strlen($text) <= $chunkSize || $delimiters === []) {
            return [$text];
        }

        $delimiter = array_shift($delimiters);

        if ($delimiter === '') {
            return str_split($text, $chunkSize);
        }

        $parts = explode($delimiter, $text);
        $splits = [];

        foreach ($parts as $index => $part) {
            // Re-add delimiter to maintain context, except for the very last part
            if ($index < count($parts) - 1) {
                $part .= $delimiter;
            }

            if ($part === '') {
                continue;
            }

            if (strlen($part) <= $chunkSize) {
                $splits[] = $part;
            } else {
                // If the part is still too large, recurse with the remaining delimiters
                $splits = array_merge($splits, $this->recursiveSplit($part, $delimiters, $chunkSize));
            }
        }

        return $splits;
    }

    /**
     * Merge small splits into chunks of approximately $chunkSize while respecting $chunkOverlap.
     *
     * @param  array<int, string>  $splits
     * @return array<int, string>
     */
    private function mergeSplits(array $splits, int $chunkSize, int $chunkOverlap): array
    {
        $chunks = [];
        $currentChunk = [];
        $currentLength = 0;

        foreach ($splits as $split) {
            $splitLength = strlen($split);

            // If adding this split exceeds the chunk size, finalize the current chunk
            if ($currentLength + $splitLength > $chunkSize && $currentChunk !== []) {
                $chunks[] = trim(implode('', $currentChunk));

                // Backtrack to create overlap for the next chunk
                $overlapContent = [];
                $overlapLength = 0;

                // Move backwards through the current pieces to collect overlap
                for ($i = count($currentChunk) - 1; $i >= 0; $i--) {
                    $piece = $currentChunk[$i];
                    if ($overlapLength + strlen($piece) <= $chunkOverlap) {
                        array_unshift($overlapContent, $piece);
                        $overlapLength += strlen($piece);
                    } else {
                        break;
                    }
                }

                $currentChunk = $overlapContent;
                $currentLength = $overlapLength;
            }

            $currentChunk[] = $split;
            $currentLength += $splitLength;
        }

        if ($currentChunk !== []) {
            $finalChunk = trim(implode('', $currentChunk));
            if ($finalChunk !== '') {
                $chunks[] = $finalChunk;
            }
        }

        return $chunks;
    }
}
