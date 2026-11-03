<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_is_available(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Create your account')
            ->assertSee('Confirm password');

        $this->get('/register')->assertOk();
    }

    public function test_user_can_register_and_is_logged_in(): void
    {
        $response = $this->post('/register', [
            'name' => 'Alex Morgan',
            'email' => 'alex@example.com',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
        ]);

        $user = User::where('email', 'alex@example.com')->firstOrFail();

        $response->assertRedirect(route('register'))
            ->assertSessionHas('status');
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('a-strong-password', $user->password));
    }

    public function test_registration_requires_a_unique_email_and_matching_password_confirmation(): void
    {
        User::factory()->create(['email' => 'alex@example.com']);

        $this->from('/register')
            ->post('/register', [
                'name' => 'Alex Morgan',
                'email' => 'alex@example.com',
                'password' => 'a-strong-password',
                'password_confirmation' => 'a-different-password',
            ])
            ->assertRedirect('/register')
            ->assertSessionHasErrors(['email', 'password']);

        $this->assertDatabaseCount('users', 1);
    }
}
