<?php

use App\Models\KnowledgeBaseFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected from the knowledge base page', function () {
    $response = $this->get(route('knowledge-base'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the knowledge base page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('knowledge-base'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('KnowledgeBase')
        );
});

test('authenticated users can visit the document listing page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $file = KnowledgeBaseFile::factory()->create([
        'name' => 'Annual Report.pdf',
        'path' => 'knowledge-base/annual-report.pdf',
        'size' => 4096,
        'status' => 'completed',
    ]);

    $this->get(route('documents'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Documents')
            ->has('files', 1)
            ->where('files.0.id', $file->id)
            ->where('files.0.name', 'Annual Report.pdf')
            ->where('files.0.status', 'completed')
        );
});

test('guests cannot upload knowledge base files', function () {
    Storage::fake('local');

    $response = $this->post(route('knowledge-base.upload'), [
        'file' => UploadedFile::fake()->create('manual.pdf', 120, 'application/pdf'),
    ]);

    $response->assertRedirect(route('login'));
});

test('authenticated users can upload pdf files to the knowledge base', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('knowledge-base.upload'), [
        'file' => UploadedFile::fake()->create('manual.pdf', 120, 'application/pdf'),
    ]);

    $response->assertRedirect(route('knowledge-base'));

    Storage::disk('local')->assertExists('knowledge-base/manual.pdf');
});

test('knowledge base uploads require a pdf file', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->postJson(route('knowledge-base.upload'), [
        'file' => UploadedFile::fake()->create('manual.txt', 10, 'text/plain'),
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});
