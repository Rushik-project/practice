<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\Employee;
use App\Models\Holiday;
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
        $this->post('/employees/1/break-in')->assertRedirect('/login');
        $this->post('/employees/1/break-out')->assertRedirect('/login');
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

    public function test_employee_dashboard_shows_current_time_and_stops_timer_at_check_out(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 5)->setTime(9, 0));
        $account = User::factory()->create();
        $employee = Employee::factory()->create(['account_id' => $account->id]);
        $this->actingAs($account);

        $this->post('/employees/'.$employee->id.'/check-in')->assertRedirect(route('employee.dashboard'));
        $checkInTimestamp = now()->timestamp;
        $this->get('/employee')
            ->assertOk()
            ->assertSee('Current time (IST):')
            ->assertSee('09:00:00')
            ->assertSee('data-timezone="Asia/Kolkata"', false)
            ->assertSee('data-work-timer', false)
            ->assertSee('data-started-at="'.$checkInTimestamp.'"', false)
            ->assertDontSee('data-stopped-at=', false);

        $this->travel(90)->minutes();
        $this->post('/employees/'.$employee->id.'/check-out')->assertRedirect(route('employee.dashboard'));
        $checkOutTimestamp = now()->timestamp;

        $this->get('/employee')
            ->assertOk()
            ->assertSee('data-started-at="'.$checkInTimestamp.'"', false)
            ->assertSee('data-stopped-at="'.$checkOutTimestamp.'"', false)
            ->assertSee('10:30:00');
    }

    public function test_employee_can_start_and_end_breaks_and_timer_excludes_break_time(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 5)->setTime(9, 0));
        $account = User::factory()->create();
        $employee = Employee::factory()->create(['account_id' => $account->id]);
        $this->actingAs($account);

        $this->post('/employees/'.$employee->id.'/break-in')->assertSessionHasErrors('attendance');
        $this->post('/employees/'.$employee->id.'/check-in')->assertRedirect(route('employee.dashboard'));
        $this->travel(2)->hours();

        $this->post('/employees/'.$employee->id.'/break-in')->assertRedirect(route('employee.dashboard'));
        $this->post('/employees/'.$employee->id.'/break-in')->assertSessionHasErrors('attendance');
        $this->post('/employees/'.$employee->id.'/check-out')->assertSessionHasErrors('attendance');
        $this->get('/employee')->assertOk()->assertSee('On break')->assertSee('Break out');
        $this->assertSame(7200, $employee->attendances()->with('breaks')->firstOrFail()->workedSeconds());

        $this->travel(30)->minutes();
        $this->post('/employees/'.$employee->id.'/break-out')->assertRedirect(route('employee.dashboard'));
        $this->assertDatabaseHas('attendance_breaks', [
            'break_started_at' => '2026-11-05 11:00:00',
            'break_ended_at' => '2026-11-05 11:30:00',
        ]);
        $this->travel(3)->hours();
        $this->post('/employees/'.$employee->id.'/check-out')->assertRedirect(route('employee.dashboard'));

        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employee->id,
            'checked_out_at' => '2026-11-05 14:30:00',
        ]);
        $this->get('/employee')->assertOk()->assertSee('05:00:00')->assertSee('5.00 h');
        $this->assertSame(18000, $employee->attendances()->with('breaks')->firstOrFail()->workedSeconds());
    }

    public function test_other_accounts_cannot_read_or_change_employees(): void
    {
        $employee = Employee::factory()->create(['name' => 'Private Employee']);
        $this->actingAs(User::factory()->admin()->create())->get('/attendance')->assertOk()->assertDontSee('Private Employee');
        $this->post('/employees/'.$employee->id.'/check-in')->assertNotFound();
        $this->post('/employees/'.$employee->id.'/check-out')->assertNotFound();
        $this->post('/employees/'.$employee->id.'/break-in')->assertNotFound();
        $this->post('/employees/'.$employee->id.'/break-out')->assertNotFound();
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

    public function test_admin_monthly_summary_counts_working_days_and_excludes_holidays_and_weekends(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 10)->setTime(12, 0));
        $admin = User::factory()->admin()->create();
        $firstEmployee = Employee::factory()->create([
            'user_id' => $admin->id,
            'name' => 'Avery Present',
            'created_at' => '2026-11-03 09:00:00',
        ]);
        $secondEmployee = Employee::factory()->create([
            'user_id' => $admin->id,
            'name' => 'Blair New Starter',
            'created_at' => '2026-11-06 09:00:00',
        ]);
        $otherEmployee = Employee::factory()->create(['name' => 'Other Admin Employee']);
        Attendance::factory()->create(['employee_id' => $firstEmployee->id, 'date' => '2026-11-03']);
        Attendance::factory()->create(['employee_id' => $firstEmployee->id, 'date' => '2026-11-07']);
        Attendance::factory()->create(['employee_id' => $firstEmployee->id, 'date' => '2026-11-09']);
        Attendance::factory()->create(['employee_id' => $secondEmployee->id, 'date' => '2026-11-06']);
        Attendance::factory()->create(['employee_id' => $otherEmployee->id, 'date' => '2026-11-03']);
        Holiday::factory()->create(['user_id' => $admin->id, 'name' => 'Company Day Off', 'date' => '2026-11-09']);

        $response = $this->actingAs($admin)->get('/admin/attendance-summary?month=2026-11');

        $response->assertOk()
            ->assertSee('Monthly attendance summary')
            ->assertSee('Avery Present')
            ->assertSee('Blair New Starter')
            ->assertDontSee('Other Admin Employee')
            ->assertViewHas('summaries', fn ($summaries): bool => $summaries->map(
                fn (array $summary): array => [
                    $summary['employee']->name,
                    $summary['working_days'],
                    $summary['present'],
                    $summary['absent'],
                    $summary['absent_dates'],
                ],
            )->all() === [
                ['Avery Present', 6, 2, 4, ['04 Nov 2026', '05 Nov 2026', '06 Nov 2026', '10 Nov 2026']],
                ['Blair New Starter', 3, 1, 2, ['07 Nov 2026', '10 Nov 2026']],
            ])
            ->assertSee('04 Nov 2026, 05 Nov 2026, 06 Nov 2026, 10 Nov 2026')
            ->assertSee('07 Nov 2026, 10 Nov 2026')
            ->assertViewHas('present', 3)
            ->assertViewHas('absent', 6)
            ->assertViewHas('workingDays', 7);
    }

    public function test_monthly_summary_is_admin_only_and_rejects_future_months(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 6)->setTime(12, 0));
        $admin = User::factory()->admin()->create();

        $this->get('/admin/attendance-summary')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin/attendance-summary')->assertForbidden();
        $this->actingAs($admin)->get('/admin/attendance-summary?month=2026-12')->assertSessionHasErrors('month');
        $this->get('/admin/attendance-summary?month=invalid')->assertSessionHasErrors('month');
    }

    public function test_employee_monthly_summary_shows_own_worked_hours_and_attendance_days(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 10)->setTime(12, 0));
        $account = User::factory()->create();
        $employee = Employee::factory()->create([
            'account_id' => $account->id,
            'created_at' => '2026-11-02 09:00:00',
        ]);
        $otherEmployee = Employee::factory()->create(['name' => 'Private Employee']);
        $firstAttendance = Attendance::factory()->create([
            'employee_id' => $employee->id,
            'date' => '2026-11-02',
            'checked_in_at' => '2026-11-02 09:00:00',
            'checked_out_at' => '2026-11-02 17:00:00',
        ]);
        AttendanceBreak::create([
            'attendance_id' => $firstAttendance->id,
            'break_started_at' => '2026-11-02 12:00:00',
            'break_ended_at' => '2026-11-02 13:00:00',
        ]);
        Attendance::factory()->create([
            'employee_id' => $employee->id,
            'date' => '2026-11-03',
            'checked_in_at' => '2026-11-03 09:00:00',
            'checked_out_at' => '2026-11-03 16:00:00',
        ]);
        Attendance::factory()->create([
            'employee_id' => $otherEmployee->id,
            'date' => '2026-11-02',
            'checked_in_at' => '2026-11-02 09:00:00',
            'checked_out_at' => '2026-11-02 17:00:00',
        ]);

        $this->actingAs($account)->get('/employee/attendance-summary?month=2026-11')
            ->assertOk()
            ->assertSee('My monthly summary')
            ->assertSee('14.00 h')
            ->assertViewHas('summary', [
                'working_days' => 8,
                'present' => 2,
                'absent' => 6,
                'worked_seconds' => 50400,
            ])
            ->assertDontSee('Private Employee');
    }

    public function test_employee_monthly_summary_is_private_and_requires_a_valid_non_future_month(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 6)->setTime(12, 0));
        $employee = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->get('/employee/attendance-summary')->assertRedirect('/login');
        $this->actingAs($admin)->get('/employee/attendance-summary')->assertForbidden();
        $this->actingAs($employee)->get('/employee/attendance-summary?month=invalid')->assertSessionHasErrors('month');
        $this->get('/employee/attendance-summary?month=2026-12')->assertSessionHasErrors('month');
    }

    public function test_logout_ends_session(): void
    {
        $this->actingAs(User::factory()->admin()->create())->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }
}
