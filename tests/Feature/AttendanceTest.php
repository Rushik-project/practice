<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_requires_authentication(): void
    {
        $this->get('/attendance')->assertRedirect('/login');
        $this->post('/employees')->assertRedirect('/login');
        $this->post('/employees/1/check-in')->assertRedirect('/login');
        $this->post('/employees/1/check-out')->assertRedirect('/login');
    }

    public function test_employee_creation_is_validated_and_owned_by_current_account(): void
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user)->post('/employees', [
            'name' => 'Alex Morgan', 'email' => 'alex@example.com', 'department' => 'Engineering', 'user_id' => 999, 'password' => 'employee-password', 'password_confirmation' => 'employee-password',
        ])->assertRedirect(route('employees.index'));
        $this->assertDatabaseHas('employees', ['name' => 'Alex Morgan', 'user_id' => $user->id]);
        $this->post('/employees', ['name' => '', 'email' => 'invalid', 'department' => ''])
            ->assertSessionHasErrors(['name', 'email', 'department']);
        $this->post('/employees', ['name' => 'Other', 'email' => 'alex@example.com', 'department' => 'HR'])
            ->assertSessionHasErrors('email');
        $this->assertDatabaseCount('employees', 1);
    }

    public function test_check_in_and_out_preserve_original_times_and_prevent_repeats(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 5)->setTime(9, 0));
        $employee = Employee::factory()->create();
        $user = User::findOrFail($employee->user_id);
        $this->actingAs($user)->post('/employees/'.$employee->id.'/check-out')->assertSessionHasErrors('attendance');
        $this->post('/employees/'.$employee->id.'/check-in')->assertRedirect(route('admin.dashboard'));
        $this->travel(1)->hours();
        $this->post('/employees/'.$employee->id.'/check-in')->assertSessionHasErrors('attendance');
        $this->travel(7)->hours();
        $this->post('/employees/'.$employee->id.'/check-out')->assertRedirect(route('admin.dashboard'));
        $this->travel(1)->hours();
        $this->post('/employees/'.$employee->id.'/check-out')->assertSessionHasErrors('attendance');
        $this->assertDatabaseCount('attendances', 1);
        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employee->id, 'date' => '2026-11-05',
            'checked_in_at' => '2026-11-05 09:00:00', 'checked_out_at' => '2026-11-05 17:00:00',
        ]);
        $this->get('/attendance')->assertOk()->assertSee('8.00 h')->assertSee('Completed');
        $this->travel(1)->days();
        $this->post('/employees/'.$employee->id.'/check-in')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('attendances', 2);
    }

    public function test_other_accounts_cannot_read_or_change_employees(): void
    {
        $employee = Employee::factory()->create(['name' => 'Private Employee']);
        $this->actingAs(User::factory()->admin()->create())->get('/attendance')->assertOk()->assertDontSee('Private Employee');
        $this->post('/employees/'.$employee->id.'/check-in')->assertNotFound();
        $this->post('/employees/'.$employee->id.'/check-out')->assertNotFound();
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_history_filters_and_escapes_employee_content(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 5)->setTime(12, 0));
        $user = User::factory()->admin()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id, 'name' => '<script>alert(1)</script>', 'department' => 'Engineering']);
        Employee::factory()->create(['user_id' => $user->id, 'name' => 'Unrelated person', 'department' => 'Sales']);
        Attendance::factory()->create(['employee_id' => $employee->id, 'date' => '2026-11-04', 'checked_in_at' => '2026-11-04 09:15:00']);
        $this->actingAs($user)->get('/attendance?date=2026-11-04&search=Engineering')
            ->assertOk()->assertSee('09:15')->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('Unrelated person')->assertDontSee('Check in</button>', false);
        $this->get('/attendance?date=invalid')->assertSessionHasErrors('date');
        $this->get('/attendance?date=2026-11-06')->assertSessionHasErrors('date');
    }

    public function test_logout_ends_session(): void
    {
        $this->actingAs(User::factory()->admin()->create())->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }
}
