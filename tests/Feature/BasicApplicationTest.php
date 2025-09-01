<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BasicApplicationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the application can start without errors.
     */
    public function test_application_can_start()
    {
        $response = $this->get('/');
        
        $response->assertStatus(200);
    }

    /**
     * Test that the landing page loads correctly.
     */
    public function test_landing_page_loads()
    {
        $response = $this->get('/');
        
        $response->assertStatus(200);
        $response->assertSee('WIFIHYPER');
    }

    /**
     * Test that login page is accessible.
     */
    public function test_login_page_is_accessible()
    {
        $response = $this->get('/login');
        
        $response->assertStatus(200);
    }

    /**
     * Test that register page is accessible.
     */
    public function test_register_page_is_accessible()
    {
        $response = $this->get('/register');
        
        $response->assertStatus(200);
    }
} 