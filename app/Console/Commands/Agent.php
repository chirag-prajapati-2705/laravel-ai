<?php

namespace App\Console\Commands;

use App\Neuron\YouTubeAgent;
use Illuminate\Console\Command;
use NeuronAI\Chat\Messages\UserMessage;

class Agent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:agent {url? : YouTube video URL}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Summarize a YouTube video (pass URL as argument or enter when prompted)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $url = $this->argument('url') ?? $this->ask('Enter YouTube video URL');

        // Print the entered URL
        $this->line("URL: $url");

        // Get the summary from the agent
        $response = YouTubeAgent::make()
            ->chat(new UserMessage("Provide a summary for the YouTube video at this URL: $url"));

        // Print the response below the URL
        $this->line("\nSummary:\n".$response->getContent());

        return 0;
    }
}
