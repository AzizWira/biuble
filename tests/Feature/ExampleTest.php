<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;
    protected $seed = true;
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_stylesheet_urls_follow_https_from_the_local_proxy(): void
    {
        $host = 'biuble-demo.ngrok-free.dev';

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('http://'.$host.'/', ['X-Forwarded-Proto' => 'https'])
            ->assertOk()
            ->assertSee('href="https://'.$host.'/design/biuble.css"', false)
            ->assertSee('href="https://'.$host.'/design/fonts.css"', false)
            ->assertSee('href="https://'.$host.'/store.css?v=', false);
    }
}

