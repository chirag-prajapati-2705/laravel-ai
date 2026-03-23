<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

test('guests cannot generate images', function () {
    $response = $this->post(route('generate-image'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can generate images', function () {
    Storage::fake('public');
    Http::fake([
        'https://api.bfl.ai/v1/get_result*' => Http::response([
            'status' => 'Ready',
            'result' => [
                'sample' => 'https://delivery.bfl.ai/sample.jpg',
            ],
        ], 200),
        'https://api.bfl.ai/v1/*' => Http::response([
            'id' => 'task-123',
            'polling_url' => 'https://api.bfl.ai/v1/get_result?id=task-123',
        ], 200),
        'https://delivery.bfl.ai/sample.jpg' => Http::response('fake-image-bytes', 200, [
            'Content-Type' => 'image/jpeg',
        ]),
    ]);
    config(['services.bfl.key' => 'test-key']);

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('generate-image'), [
        'image' => UploadedFile::fake()->image('reference.jpg'),
        'prompt' => 'A watercolor landscape with mountains.',
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure(['generated_url', 'input_image_url']);

    expect(Storage::disk('public')->allFiles('image-generator'))->toHaveCount(2);
});

test('image generation requires an image and prompt', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->postJson(route('generate-image'), []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['image', 'prompt']);
});
