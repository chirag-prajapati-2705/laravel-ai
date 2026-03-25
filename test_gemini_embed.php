<?php

require __DIR__.'/vendor/autoload.php';

$key = 'AIzaSyB4NBqc2M5jVWhkHjU3sL9IMRRepBE8ehI'; // User's GEMINI API KEY from .env
$model = 'text-embedding-004';

$client = new \GuzzleHttp\Client;
try {
    $response = $client->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:embedContent?key={$key}", [
        'json' => [
            'model' => "models/{$model}",
            'content' => [
                'parts' => [['text' => 'Hello world']],
            ],
        ],
    ]);
    echo 'Status code: '.$response->getStatusCode()."\n";
    $data = json_decode($response->getBody()->getContents(), true);
    echo 'Dimensions: '.count($data['embedding']['values'])."\n";
} catch (\GuzzleHttp\Exception\ClientException $e) {
    echo 'Failed: '.$e->getResponse()->getBody()->getContents()."\n";
}
