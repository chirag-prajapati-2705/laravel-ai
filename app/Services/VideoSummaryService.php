<?php

namespace App\Services;

use App\Neuron\YouTubeAgent;
use NeuronAI\Chat\Enums\MessageRole;
use NeuronAI\Chat\Messages\Message;

class VideoSummaryService
{
    public function summarizeFromUrl(string $url): array
    {
        $agent = new YouTubeAgent();

        $response = $agent->chat(
            new Message(
                role: MessageRole::USER,
                content: 'Summarize this YouTube video: ' . $url,
            )
        );

        $content = $response->getContent();

        // Split the response into summary + takeaways.
        $parts = preg_split('/\n{2,}/', $content);
        $summary = $parts[0] ?? $content;
        $takeaways = [];

        if (count($parts) > 1) {
            // Extract bullet points / numbered items from the remaining text.
            $remaining = implode("\n", array_slice($parts, 1));
            preg_match_all('/(?:^|\n)\s*(?:\d+[\.\)]\s*|-\s*|\*\s*)(.*)/m', $remaining, $matches);

            if (!empty($matches[1])) {
                $takeaways = array_map('trim', $matches[1]);
            } else {
                // No bullet format found, just use the remaining paragraphs.
                $takeaways = array_filter(array_map('trim', array_slice($parts, 1)));
            }
        }

        return [
            'summary' => trim($summary),
            'takeaways' => array_values($takeaways),
        ];
    }
}
