<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class YandexReviewsApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    /**
     * Test login endpoint with valid credentials.
     */
    public function test_user_can_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ], [
            'referer' => 'http://localhost',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'email', 'name']
            ]);

        $this->assertAuthenticatedAs($this->user);
    }

    /**
     * Test login endpoint with invalid credentials.
     */
    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ], [
            'referer' => 'http://localhost',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test endpoint protection.
     */
    public function test_unauthenticated_user_cannot_access_organization(): void
    {
        $response = $this->getJson('/api/organization');
        $response->assertStatus(401);
    }

    /**
     * Test get organization endpoint.
     */
    public function test_authenticated_user_can_get_organization(): void
    {
        $organization = Organization::create([
            'yandex_id' => '1124715036',
            'url' => 'https://yandex.ru/maps/org/1124715036',
            'name' => 'Yandex',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user, 'web')
            ->getJson('/api/organization');

        $response->assertStatus(200)
            ->assertJsonPath('organization.name', 'Yandex')
            ->assertJsonPath('organization.yandex_id', '1124715036');
    }

    /**
     * Test setting validation with invalid URL.
     */
    public function test_settings_validation_rejects_invalid_url(): void
    {
        $response = $this->actingAs($this->user, 'web')
            ->postJson('/api/organization/settings', [
                'url' => 'https://google.com/maps',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['url']);
    }

    /**
     * Test settings save and parser job dispatching.
     */
    public function test_settings_saves_url_and_dispatches_parser_job(): void
    {
        Queue::fake();

        $url = 'https://yandex.ru/maps/org/yandex/1124715036/';
        
        $response = $this->actingAs($this->user, 'web')
            ->postJson('/api/organization/settings', [
                'url' => $url,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'organization']);

        $this->assertDatabaseHas('organizations', [
            'yandex_id' => '1124715036',
            'url' => $url,
            'status' => 'pending',
        ]);

        Queue::assertPushed(\App\Jobs\ParseOrganizationJob::class);
    }

    /**
     * Test reviews pagination endpoint.
     */
    public function test_user_can_get_paginated_reviews(): void
    {
        $organization = Organization::create([
            'yandex_id' => '1124715036',
            'url' => 'https://yandex.ru/maps/org/1124715036',
            'name' => 'Yandex',
            'status' => 'completed',
        ]);

        // Seed 10 test reviews
        for ($i = 1; $i <= 10; $i++) {
            Review::create([
                'organization_id' => $organization->id,
                'author_name' => "Author $i",
                'rating' => 5,
                'text' => "Great place $i",
                'published_at_str' => '1 день назад',
            ]);
        }

        $response = $this->actingAs($this->user, 'web')
            ->getJson('/api/organization/reviews');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'current_page',
                'data' => [
                    '*' => ['id', 'organization_id', 'author_name', 'rating', 'text', 'published_at_str']
                ],
                'total',
                'per_page'
            ])
            ->assertJsonPath('total', 10);
    }
}
