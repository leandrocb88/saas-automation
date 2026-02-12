<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_youtube_user_can_login_on_youtube_domain(): void
    {
        $user = User::factory()->create([
            'email' => 'youtube@test.com',
            'service_type' => 'youtube',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('http://youtube.saas.test/login', [
            'email' => 'youtube@test.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/dashboard');
    }

    public function test_zillow_user_CANNOT_login_on_youtube_domain(): void
    {
        $user = User::factory()->create([
            'email' => 'zillow@test.com',
            'service_type' => 'zillow',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('http://youtube.saas.test/login', [
            'email' => 'zillow@test.com', // Trying to login with Zillow account...
            'password' => 'password',
        ]); // ... on YouTube domain

        $this->assertGuest(); // Should fail
        $response->assertSessionHasErrors('email');
    }

    public function test_dashboard_shows_youtube_context_on_youtube_domain(): void
    {
        $user = User::factory()->create([
            'service_type' => 'youtube',
        ]);

        $response = $this->actingAs($user)
            ->get('http://youtube.saas.test/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('service', 'Youtube') // Check if 'service' prop matches. (Note: ucfirst('youtube') -> Youtube)
        );
    }

    public function test_dashboard_shows_zillow_context_on_zillow_domain(): void
    {
        $user = User::factory()->create([
            'service_type' => 'zillow',
        ]);

        $response = $this->actingAs($user)
            ->get('http://zillow.saas.test/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('service', 'Zillow')
        );
    }
}
