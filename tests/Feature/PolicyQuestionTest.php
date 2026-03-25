<?php

use App\Models\User;
use App\Services\PolicyQuestionAnswerService;

test('guests cannot access policy q&a routes', function () {
    $this->get(route('policy-qa'))
        ->assertRedirect(route('login'));

    $this->post(route('policy-question'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view the policy q&a page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('policy-qa'))
        ->assertOk();
});

test('policy questions require a question', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->postJson(route('policy-question'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['question']);
});

test('authenticated users can ask a policy question', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $service = \Mockery::mock(PolicyQuestionAnswerService::class);
    $service->shouldReceive('answer')
        ->once()
        ->with('What is the policy term?', null)
        ->andReturn([
            'answer' => 'The policy term is three years.',
            'sources' => ['Excerpt 1: The policy term is three years.'],
            'document' => 'policy.pdf',
        ]);

    app()->instance(PolicyQuestionAnswerService::class, $service);

    $this->postJson(route('policy-question'), [
        'question' => 'What is the policy term?',
    ])->assertSuccessful()
        ->assertJsonStructure(['answer', 'sources', 'document']);
});
