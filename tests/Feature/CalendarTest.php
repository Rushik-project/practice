<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_employee_dashboards_show_india_holidays_and_their_company_calendar(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();
        Employee::factory()->create(['user_id' => $admin->id, 'account_id' => $employee->id]);
        Holiday::factory()->create(['user_id' => $admin->id, 'name' => 'Company Anniversary', 'date' => '2026-01-15']);
        Holiday::factory()->create(['name' => 'Private Company Holiday', 'date' => '2026-01-15']);
        foreach ([[$admin, '/admin'], [$employee, '/employee']] as [$user, $path]) {
            $this->actingAs($user)->get($path.'?month=2026-01')->assertOk()
                ->assertSee('Calendar')->assertSee('January 2026')->assertSee('Republic Day')
                ->assertSee('Independence Day')->assertSee("Mahatma Gandhi's Birthday")->assertSee('26 Jan 2026')
                ->assertSee('15 Aug 2026')->assertSee('02 Oct 2026')->assertSee('Company Anniversary')
                ->assertDontSee('Private Company Holiday')->assertSee('month=2025-12', false)->assertSee('month=2026-02', false)
                ->assertSeeInOrder(['Daily attendance', 'Calendar · January 2026']);
        }
    }

    public function test_calendar_handles_leap_year_and_today_and_validates_months(): void
    {
        $this->travelTo(now()->setDate(2028, 2, 29)->setTime(9, 0));
        $this->actingAs(User::factory()->admin()->create())->get('/admin?month=2028-02')->assertOk()
            ->assertSee('February 2028')->assertSee('datetime="2028-02-29"', false)->assertSee('aria-current="date"', false)
            ->assertViewHas('calendar', function (array $calendar): bool {
                return $calendar['weeks'][0][0]['date']->toDateString() === '2028-01-31'
                    && count($calendar['weeks']) === 5;
            });
        foreach (['invalid', '2026-13', '1999-12', '2101-01'] as $month) {
            $this->get('/admin?month='.$month)->assertSessionHasErrors('month');
        }
    }

    public function test_month_navigation_retains_attendance_filters_and_calendar_escapes_names(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 5));
        $admin = User::factory()->admin()->create();
        Holiday::factory()->create(['user_id' => $admin->id, 'name' => '<script>alert(1)</script>', 'date' => '2026-11-10']);
        $this->actingAs($admin)->get('/admin?month=2026-11&date=2026-11-01&search=Design')->assertOk()
            ->assertSee('month=2026-10', false)->assertSee('date=2026-11-01', false)->assertSee('search=Design', false)
            ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_unlinked_employee_sees_national_holidays_without_company_data(): void
    {
        Holiday::factory()->create(['name' => 'Private Holiday', 'date' => '2026-01-15']);
        $this->actingAs(User::factory()->create())->get('/employee?month=2026-01')->assertOk()
            ->assertSee('Republic Day')->assertDontSee('Private Holiday');
    }
}
