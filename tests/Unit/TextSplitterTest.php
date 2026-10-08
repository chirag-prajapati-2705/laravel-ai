<?php

use App\Services\TextSplitter;

test('it splits text by paragraphs', function () {
    $splitter = new TextSplitter;
    $text = "Paragraph 1\n\nParagraph 2\n\nParagraph 3";

    // Each paragraph + delimiter is ~13 chars. 15 ensures only one paragraph fits.
    $chunks = $splitter->split($text, 15, 0);

    expect($chunks)->toHaveCount(3)
        ->and($chunks[0])->toBe('Paragraph 1')
        ->and($chunks[1])->toBe('Paragraph 2')
        ->and($chunks[2])->toBe('Paragraph 3');
});

test('it respects chunk size and overlap', function () {
    $splitter = new TextSplitter;
    $text = 'This is a long sentence that should be split into multiple chunks.';

    // Split into chunks of ~20 chars with 10 overlap
    $chunks = $splitter->split($text, 20, 10);

    expect($chunks)->not->toBeEmpty();
    foreach ($chunks as $chunk) {
        expect(strlen($chunk))->toBeLessThanOrEqual(20);
    }
});

test('it preserves context with overlap', function () {
    $splitter = new TextSplitter;
    $text = 'Part One. Part Two. Part Three.';

    // delimiters: [" ", ""]
    $chunks = $splitter->split($text, 15, 10, [' ', '']);

    // Check if overlap exists (the end of chunk 0 should be at start of chunk 1)
    if (count($chunks) > 1) {
        $overlapFound = false;
        for ($i = 0; $i < count($chunks) - 1; $i++) {
            $end = substr($chunks[$i], -5);
            if (str_contains($chunks[$i + 1], $end)) {
                $overlapFound = true;
                break;
            }
        }
        expect($overlapFound)->toBeTrue();
    }
});
