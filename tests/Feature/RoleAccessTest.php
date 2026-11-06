<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
        $this->get('/employee')->assertOk()->assertDontSee('Add employee')->assertDontSee('Employee list')->assertDontSee('Update target');
        $this->assertDatabaseCount('employees', 1);
    }

    public function test_admin_and_employee_can_open_profile_from_dashboard_settings(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin Profile', 'email' => 'admin-profile@example.com']);
        $employee = User::factory()->create(['name' => 'Employee Profile', 'email' => 'employee-profile@example.com']);

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('Settings')
            ->assertSee(route('profile'));
        $this->get(route('profile'))
            ->assertOk()
            ->assertSee('Admin Profile')
            ->assertSee('admin-profile@example.com')
            ->assertSee('Administrator')
            ->assertSee('Log out')
            ->assertSee('action="'.route('logout').'"', false)
            ->assertSee('method="POST"', false);

        $this->post('/logout');
        $this->actingAs($employee)->get('/employee')
            ->assertOk()
            ->assertSee('Settings')
            ->assertSee(route('profile'));
        $this->get(route('profile'))
            ->assertOk()
            ->assertSee('Employee Profile')
            ->assertSee('employee-profile@example.com')
            ->assertSee('Employee')
            ->assertSee('Log out')
            ->assertSee('action="'.route('logout').'"', false)
            ->assertSee('method="POST"', false);
    }

    public function test_profile_requires_authentication(): void
    {
        $this->get(route('profile'))->assertRedirect(route('login'));
    }

    public function test_admin_and_employee_can_upload_and_replace_their_own_profile_photo(): void
    {
        Storage::fake('public');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l2cAAAAASUVORK5CYII=', true);
        $admin = User::factory()->admin()->create(['name' => 'Admin User']);
        $employee = User::factory()->create();

        $this->actingAs($admin)->post(route('profile.photo'), [
            'profile_photo' => UploadedFile::fake()->createWithContent('admin.png', $png),
        ])->assertRedirect(route('profile'))->assertSessionHas('status', 'Profile photo updated.');
        $adminPhotoPath = $admin->fresh()->profile_photo_path;
        Storage::disk('public')->assertExists($adminPhotoPath);
        $this->get(route('profile'))
            ->assertOk()
            ->assertSee('alt="Admin User profile photo"', false);

        $this->post('/logout');
        $this->actingAs($employee)->post(route('profile.photo'), [
            'profile_photo' => UploadedFile::fake()->createWithContent('employee.png', $png),
        ])->assertRedirect(route('profile'));
        $employeePhotoPath = $employee->fresh()->profile_photo_path;
        Storage::disk('public')->assertExists($employeePhotoPath);
        $this->assertDatabaseHas('users', ['id' => $employee->id, 'profile_photo_path' => $employeePhotoPath]);

        $this->post('/logout');
        $this->actingAs($admin)->post(route('profile.photo'), [
            'profile_photo' => UploadedFile::fake()->createWithContent('replacement.png', $png),
        ])->assertRedirect(route('profile'));

        Storage::disk('public')->assertMissing($adminPhotoPath);
        Storage::disk('public')->assertExists($admin->fresh()->profile_photo_path);
        Storage::disk('public')->assertExists($employeePhotoPath);
    }

    public function test_profile_photo_upload_rejects_non_images_and_oversized_files(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.photo'), [
            'profile_photo' => UploadedFile::fake()->create('document.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors('profile_photo');

        $this->post(route('profile.photo'), [
            'profile_photo' => UploadedFile::fake()->create('large.png', 2049, 'image/png'),
        ])->assertSessionHasErrors('profile_photo');

        $this->assertNull($user->fresh()->profile_photo_path);
        $this->assertSame([], Storage::disk('public')->allFiles('profile-photos'));
    }

    public function test_admin_can_update_and_employees_can_view_their_daily_working_hours_target(): void
    {
        $admin = User::factory()->admin()->create();
        $account = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $admin->id, 'account_id' => $account->id]);

        $this->actingAs($admin)->patch('/admin/employees/'.$employee->id.'/working-hours', [
            'daily_working_hours' => '7.5',
        ])->assertRedirect(route('employees.index'))->assertSessionHas('status', 'Daily working hours updated.');

        $this->assertSame('7.50', $employee->fresh()->daily_working_hours);
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'daily_working_hours' => 7.5]);
        $this->get('/admin/employees')->assertOk()->assertSee('Update target')->assertSee('value="7.50"', false);
        $this->post('/logout');
        $this->actingAs($account)->get('/employee')->assertOk()->assertSee('7.50 h');
    }

    public function test_admin_can_update_employee_details_and_their_linked_login(): void
    {
        $admin = User::factory()->admin()->create();
        $account = User::factory()->create(['name' => 'Original Name', 'email' => 'original@example.com']);
        $employee = Employee::factory()->create([
            'user_id' => $admin->id,
            'account_id' => $account->id,
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'department' => 'Engineering',
        ]);

        $this->actingAs($admin)->get('/admin/employees')
            ->assertOk()
            ->assertSee('Edit details')
            ->assertSee('employee-name-'.$employee->id, false);
        $this->patch(route('employees.update', $employee), [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'department' => 'Design',
        ])->assertRedirect(route('employees.index'))
            ->assertSessionHas('status', 'Employee details updated.');

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'department' => 'Design',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $account->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);
    }

    public function test_employee_details_cannot_be_updated_by_non_admin_or_another_admin(): void
    {
        $employeeAccount = User::factory()->create();
        $employee = Employee::factory()->create(['account_id' => $employeeAccount->id]);
        $otherAdmin = User::factory()->admin()->create();
        $payload = ['name' => 'Changed', 'email' => 'changed@example.com', 'department' => 'Design'];

        $this->patch(route('employees.update', $employee), $payload)->assertRedirect('/login');
        $this->actingAs($employeeAccount)->patch(route('employees.update', $employee), $payload)->assertForbidden();
        $this->actingAs($otherAdmin)->patch(route('employees.update', $employee), $payload)->assertNotFound();

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
            'department' => $employee->department,
        ]);
    }

    public function test_employee_details_require_unique_emails_and_valid_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create(['user_id' => $admin->id]);
        $otherEmployee = Employee::factory()->create(['user_id' => $admin->id]);
        $anotherAccount = User::factory()->create(['email' => 'taken@example.com']);
        Employee::factory()->create(['user_id' => $admin->id, 'account_id' => $anotherAccount->id, 'email' => 'taken@example.com']);
        $this->actingAs($admin);

        $this->patch(route('employees.update', $employee), [
            'name' => '',
            'email' => 'invalid',
            'department' => '',
        ])->assertSessionHasErrors(['name', 'email', 'department']);
        $this->patch(route('employees.update', $employee), [
            'name' => 'Changed',
            'email' => $otherEmployee->email,
            'department' => 'Design',
        ])->assertSessionHasErrors('email');
        $this->patch(route('employees.update', $employee), [
            'name' => 'Changed',
            'email' => 'taken@example.com',
            'department' => 'Design',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'name' => $employee->name]);
    }

    public function test_employees_cannot_change_working_hours_and_admins_cannot_change_another_admins_employee(): void
    {
        $employeeAccount = User::factory()->create();
        $employee = Employee::factory()->create(['account_id' => $employeeAccount->id]);
        $otherAdmin = User::factory()->admin()->create();

        $this->patch('/admin/employees/'.$employee->id.'/working-hours', [
            'daily_working_hours' => '6',
        ])->assertRedirect('/login');
        $this->actingAs($employeeAccount)->patch('/admin/employees/'.$employee->id.'/working-hours', [
            'daily_working_hours' => '6',
        ])->assertForbidden();
        $this->actingAs($otherAdmin)->patch('/admin/employees/'.$employee->id.'/working-hours', [
            'daily_working_hours' => '6',
        ])->assertNotFound();

        $this->assertSame('8.00', $employee->fresh()->daily_working_hours);
    }

    public function test_working_hour_targets_must_be_between_one_and_twenty_four_in_quarter_hour_steps(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create(['user_id' => $admin->id]);

        $this->actingAs($admin);
        foreach (['0.5', '24.5', '7.1'] as $invalidTarget) {
            $this->patch('/admin/employees/'.$employee->id.'/working-hours', [
                'daily_working_hours' => $invalidTarget,
            ])->assertSessionHasErrors('daily_working_hours');
        }

        $this->assertSame('8.00', $employee->fresh()->daily_working_hours);
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

    public function test_employee_can_view_colleagues_in_their_company_with_department_email_and_photo(): void
    {
        Storage::fake('public');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l2cAAAAASUVORK5CYII=', true);
        $admin = User::factory()->admin()->create();
        $account = User::factory()->create();
        $colleagueAccount = User::factory()->create([
            'profile_photo_path' => UploadedFile::fake()->createWithContent('colleague.png', $png)->store('profile-photos', 'public'),
        ]);
        Employee::factory()->create([
            'user_id' => $admin->id,
            'account_id' => $account->id,
            'name' => 'Current Employee',
        ]);
        Employee::factory()->create([
            'user_id' => $admin->id,
            'account_id' => $colleagueAccount->id,
            'name' => 'Team Colleague',
            'email' => 'colleague@example.com',
            'department' => "Design <script>alert('x')</script>",
        ]);
        Employee::factory()->create([
            'name' => 'Other Company Employee',
            'email' => 'other-company@example.com',
        ]);

        $this->actingAs($account)->get(route('employee.colleagues'))
            ->assertOk()
            ->assertSee('Colleagues')
            ->assertSee('Team Colleague')
            ->assertSee('Design')
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee("<script>alert('x')</script>", false)
            ->assertSee('colleague@example.com')
            ->assertSee('mailto:colleague@example.com')
            ->assertSee(Storage::disk('public')->url($colleagueAccount->profile_photo_path))
            ->assertDontSee('Current Employee')
            ->assertDontSee('Other Company Employee')
            ->assertDontSee('other-company@example.com');
    }

    public function test_colleague_directory_requires_a_linked_employee_account_and_rejects_admins(): void
    {
        $admin = User::factory()->admin()->create();
        $unlinkedAccount = User::factory()->create();

        $this->get(route('employee.colleagues'))->assertRedirect(route('login'));
        $this->actingAs($admin)->get(route('employee.colleagues'))->assertForbidden();
        $this->actingAs($unlinkedAccount)->get(route('employee.colleagues'))->assertNotFound();
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
