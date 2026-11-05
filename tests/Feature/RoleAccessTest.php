<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_cannot_access_admin_pages_or_create_accounts(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $this->actingAs($user)->get('/admin')->assertForbidden();
        $this->get('/admin/employees')->assertForbidden();
        $this->post('/employees', ['role' => 'admin'])->assertForbidden();
        $this->post('/admin/employees/'.$employee->id.'/account')->assertForbidden();
        $this->get('/employee')->assertOk()->assertDontSee('Add employee')->assertDontSee('Employee list');
        $this->assertDatabaseCount('employees', 1);
    }

    public function test_admin_can_list_and_create_employee_login_without_granting_admin_access(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Admin dashboard')->assertSee('Employee list');
        $this->post('/employees', [
            'name' => 'Taylor Employee', 'email' => 'taylor@example.com', 'department' => 'Design',
            'password' => 'employee-password', 'password_confirmation' => 'employee-password', 'role' => 'admin', 'account_id' => $admin->id,
        ])->assertRedirect(route('employees.index'))->assertSessionHasNoErrors();
        $account = User::where('email', 'taylor@example.com')->firstOrFail();
        $this->assertSame('employee', $account->role);
        $this->assertTrue(Hash::check('employee-password', $account->password));
        $this->assertDatabaseHas('employees', ['user_id' => $admin->id, 'account_id' => $account->id, 'name' => 'Taylor Employee']);
        $this->get('/admin/employees')->assertOk()->assertSee('Taylor Employee')->assertSee('Add employee')->assertSee('Account active');
        $this->post('/logout');
        $this->post('/login', ['email' => 'taylor@example.com', 'password' => 'employee-password'])->assertRedirect(route('employee.dashboard'));
        $this->get('/employee')->assertOk()->assertSee('Taylor Employee');
    }

    public function test_employee_can_only_record_and_view_their_own_attendance(): void
    {
        $this->travelTo(now()->setTime(9, 0));
        $account = User::factory()->create();
        $employee = Employee::factory()->create(['account_id' => $account->id, 'name' => 'My Profile']);
        $other = Employee::factory()->create(['user_id' => $employee->user_id, 'name' => 'Other Employee']);
        $this->actingAs($account)->get('/employee')->assertOk()->assertSee('My Profile')->assertDontSee('Other Employee');
        $this->get('/attendance')->assertOk()->assertDontSee('Other Employee');
        $this->post('/employees/'.$other->id.'/check-in')->assertNotFound();
        $this->post('/employees/'.$other->id.'/check-out')->assertNotFound();
        $this->post('/employees/'.$employee->id.'/check-in')->assertRedirect(route('employee.dashboard'));
        $this->travel(8)->hours();
        $this->post('/employees/'.$employee->id.'/check-out')->assertRedirect(route('employee.dashboard'));
        $this->assertDatabaseCount('attendances', 1);
        $this->assertDatabaseHas('attendances', ['employee_id' => $employee->id]);
    }

    public function test_existing_employee_can_receive_a_login_only_from_its_admin(): void
    {
        $employee = Employee::factory()->create();
        $payload = ['account_email' => 'legacy@example.com', 'password' => 'employee-password', 'password_confirmation' => 'employee-password'];
        $this->actingAs(User::factory()->admin()->create())->post('/admin/employees/'.$employee->id.'/account', $payload)->assertNotFound();
        $this->actingAs(User::findOrFail($employee->user_id))->post('/admin/employees/'.$employee->id.'/account', $payload)
            ->assertRedirect(route('employees.index'));
        $account = User::where('email', 'legacy@example.com')->firstOrFail();
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'account_id' => $account->id]);
        $this->assertSame('employee', $account->role);
        $this->post('/admin/employees/'.$employee->id.'/account', $payload)->assertConflict();
    }

    public function test_public_registration_cannot_choose_admin_role(): void
    {
        $this->post('/register', [
            'name' => 'Public User', 'email' => 'public@example.com', 'password' => 'employee-password',
            'password_confirmation' => 'employee-password', 'role' => 'admin',
        ])->assertRedirect(route('employee.dashboard'));
        $this->assertDatabaseHas('users', ['email' => 'public@example.com', 'role' => 'employee']);
        $this->get('/admin')->assertForbidden();
    }

    public function test_admin_employee_list_is_private_and_searchable(): void
    {
        $admin = User::factory()->admin()->create();
        Employee::factory()->create(['user_id' => $admin->id, 'name' => 'Design Person', 'department' => 'Design']);
        Employee::factory()->create(['user_id' => $admin->id, 'name' => 'Finance Person', 'department' => 'Finance']);
        Employee::factory()->create(['name' => 'Private Person']);
        $this->actingAs($admin)->get('/admin/employees?search=Design')->assertOk()->assertSee('Design Person')->assertDontSee('Finance Person')->assertDontSee('Private Person');
    }

    public function test_admin_login_opens_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_promotion_command_updates_the_selected_account(): void
    {
        $account = User::factory()->create();
        $this->artisan('app:make-admin', ['email' => $account->email])->assertSuccessful();
        $this->assertTrue($account->fresh()->isAdmin());
    }
}
