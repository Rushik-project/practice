<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_available(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Welcome back')
            ->assertSee('name="remember"', false);
    }

    public function test_user_can_log_in_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'alex@example.com',
            'password' => Hash::make('a-strong-password'),
        ]);

        $this->post('/login', [
            'email' => 'alex@example.com',
            'password' => 'a-strong-password',
            'remember' => '1',
        ])
            ->assertRedirect(route('register'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_do_not_log_in(): void
    {
        User::factory()->create([
            'email' => 'alex@example.com',
            'password' => Hash::make('a-strong-password'),
        ]);

        $this->from('/login')
            ->post('/login', [
                'email' => 'alex@example.com',
                'password' => 'incorrect-password',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
