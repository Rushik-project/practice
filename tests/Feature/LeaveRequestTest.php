<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_submits_pending_request_for_their_own_profile(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 5)->setTime(9, 0));
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['account_id' => $user->id]);
        $other = Employee::factory()->create();
        $this->actingAs($user)->get('/employee/leave')->assertOk()->assertSee('Apply for leave');
        $this->post('/employee/leave', [
            'start_date' => '2026-11-06', 'end_date' => '2026-11-07', 'reason' => 'Family event',
            'employee_id' => $other->id, 'status' => 'approved', 'reviewed_by' => $user->id,
        ])->assertRedirect(route('employee.leave'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leave_requests', [
            'employee_id' => $employee->id, 'reason' => 'Family event', 'status' => 'pending', 'reviewed_by' => null,
        ]);
        $this->get('/employee/leave')->assertOk()->assertSee('Family event')->assertSee('Pending');
        $this->actingAs(User::findOrFail($employee->user_id))->get('/admin/leave')->assertOk()->assertSee('Family event')->assertSee('Approve')->assertSee('Reject');
    }

    public function test_requests_require_valid_current_or_future_dates_and_a_reason(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 5));
        $user = User::factory()->create();
        Employee::factory()->create(['account_id' => $user->id]);
        $valid = ['start_date' => '2026-11-06', 'end_date' => '2026-11-07', 'reason' => 'Appointment'];
        $this->actingAs($user);
        foreach ([
            ['start_date', '2026-11-04'], ['start_date', 'invalid'], ['end_date', '2026-11-05'],
            ['end_date', 'invalid'], ['reason', ''], ['reason', str_repeat('a', 2001)],
        ] as [$field, $value]) {
            $this->post('/employee/leave', array_replace($valid, [$field => $value]))->assertSessionHasErrors($field);
        }
        $this->assertDatabaseCount('leave_requests', 0);
    }

    public function test_overlapping_pending_and_approved_requests_are_blocked_but_rejected_dates_can_be_reapplied(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 5));
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['account_id' => $user->id]);
        $leave = LeaveRequest::factory()->create(['employee_id' => $employee->id, 'start_date' => '2026-11-06', 'end_date' => '2026-11-08']);
        $payload = ['start_date' => '2026-11-08', 'end_date' => '2026-11-09', 'reason' => 'Appointment'];
        $this->actingAs($user)->post('/employee/leave', $payload)->assertSessionHasErrors('start_date');
        $leave->status = 'approved';
        $leave->save();
        $this->post('/employee/leave', $payload)->assertSessionHasErrors('start_date');
        $leave->status = 'rejected';
        $leave->save();
        $this->post('/employee/leave', $payload)->assertRedirect(route('employee.leave'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('leave_requests', 2);
    }

    public function test_admin_can_approve_or_reject_pending_requests_and_employee_sees_decisions(): void
    {
        $this->freezeTime();
        $admin = User::factory()->admin()->create();
        $account = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $admin->id, 'account_id' => $account->id]);
        foreach (['approved', 'rejected'] as $status) {
            $leave = LeaveRequest::factory()->create(['employee_id' => $employee->id]);
            $this->actingAs($admin)->patch('/admin/leave/'.$leave->id, ['status' => $status])->assertRedirect(route('admin.leave'));
            $this->assertDatabaseHas('leave_requests', ['id' => $leave->id, 'status' => $status, 'reviewed_by' => $admin->id, 'reviewed_at' => now()->format('Y-m-d H:i:s')]);
            $this->actingAs($account)->get('/employee/leave')->assertOk()->assertSee(ucfirst($status));
        }
    }

    public function test_review_is_validated_and_decision_cannot_be_overwritten(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create(['user_id' => $admin->id]);
        $leave = LeaveRequest::factory()->create(['employee_id' => $employee->id]);
        $this->actingAs($admin)->patch('/admin/leave/'.$leave->id, ['status' => 'invalid'])->assertSessionHasErrors('status');
        $this->patch('/admin/leave/'.$leave->id, ['status' => 'approved'])->assertRedirect(route('admin.leave'));
        $this->patch('/admin/leave/'.$leave->id, ['status' => 'rejected'])->assertSessionHasErrors('leave');
        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id, 'status' => 'approved']);
    }

    public function test_leave_pages_and_actions_enforce_roles_and_account_boundaries(): void
    {
        $leave = LeaveRequest::factory()->create(['reason' => 'Private leave reason']);
        $this->get('/employee/leave')->assertRedirect('/login');
        $this->get('/admin/leave')->assertRedirect('/login');
        $this->post('/employee/leave')->assertRedirect('/login');
        $this->patch('/admin/leave/'.$leave->id)->assertRedirect('/login');
        $employeeAccount = User::factory()->create();
        $this->actingAs($employeeAccount)->get('/admin/leave')->assertForbidden();
        $this->patch('/admin/leave/'.$leave->id, ['status' => 'approved'])->assertForbidden();
        $this->get('/employee/leave')->assertOk()->assertDontSee('Private leave reason');
        $otherAdmin = User::factory()->admin()->create();
        $this->actingAs($otherAdmin)->get('/admin/leave')->assertOk()->assertDontSee('Private leave reason');
        $this->patch('/admin/leave/'.$leave->id, ['status' => 'approved'])->assertNotFound();
        $this->get('/employee/leave')->assertForbidden();
        $this->post('/employee/leave')->assertForbidden();
        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id, 'status' => 'pending']);
    }

    public function test_unlinked_account_cannot_submit_and_leave_reason_is_escaped(): void
    {
        $this->freezeTime();
        $account = User::factory()->create();
        $payload = ['start_date' => today()->toDateString(), 'end_date' => today()->toDateString(), 'reason' => '<script>alert(1)</script>'];
        $this->actingAs($account)->post('/employee/leave', $payload)->assertSessionHasErrors('leave');
        $this->assertDatabaseCount('leave_requests', 0);
        $employee = Employee::factory()->create(['account_id' => $account->id]);
        $this->post('/employee/leave', $payload)->assertRedirect(route('employee.leave'));
        $this->get('/employee/leave')->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->actingAs(User::findOrFail($employee->user_id))->get('/admin/leave')->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }
}
