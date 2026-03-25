<?php

declare(strict_types=1);

namespace App\Neuron;

use NeuronAI\Agent;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\SystemPrompt;

class PolicyDocumentAgent extends Agent
{
    protected function provider(): AIProviderInterface
    {
        return new Gemini(
            key: config('services.gemini.key'),
            model: config('services.gemini.model', 'gemini-2.5-flash'),
        );
    }

    public function instructions(): string
    {
        return (string) new SystemPrompt(
            background: [
                'You are an AI assistant that answers questions about an insurance policy document.',
                'Use only the provided document when answering.',
            ],
            steps: [
                'Read the question carefully.',
                'Answer using the policy document content.',
            ],
            output: [
                'If the answer is not in the provided document, say you do not know.',
                'Do not invent details or assumptions.',
            ],
        );
    }
}
