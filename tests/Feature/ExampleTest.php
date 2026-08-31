<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Guests hitting / should be redirected to login (route is auth-protected).
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertRedirect();
    }

    /**
     * Public API health check should return 200 JSON.
     */
    public function test_api_test_endpoint_is_available(): void
    {
        $response = $this->getJson('/api/test');

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    }
}
