<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidayTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_adds_holiday_visible_to_their_employees_only(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();
        Employee::factory()->create(['user_id' => $admin->id, 'account_id' => $employee->id]);
        $this->actingAs($admin)->post('/holidays', ['name' => 'Company Day', 'date' => '2026-12-01', 'user_id' => 999])
            ->assertRedirect(route('holidays.index', ['year' => 2026]));
        $this->assertDatabaseHas('holidays', ['user_id' => $admin->id, 'name' => 'Company Day']);
        $this->actingAs($employee)->get('/holidays?year=2026')->assertOk()->assertSee('Company Day')->assertSee('01 Dec 2026')->assertSee('Tuesday')->assertDontSee('Add holiday');
        $this->actingAs(User::factory()->admin()->create())->get('/holidays?year=2026')->assertOk()->assertDontSee('Company Day');
    }

    public function test_holidays_are_filtered_by_year_and_ordered_by_date(): void
    {
        $admin = User::factory()->admin()->create();
        Holiday::factory()->create(['user_id' => $admin->id, 'name' => 'Later Holiday', 'date' => '2026-12-01']);
        Holiday::factory()->create(['user_id' => $admin->id, 'name' => 'Earlier Holiday', 'date' => '2026-01-01']);
        Holiday::factory()->create(['user_id' => $admin->id, 'name' => 'Other Year', 'date' => '2027-01-01']);
        $this->actingAs($admin)->get('/holidays?year=2026')->assertOk()->assertSeeInOrder(['Earlier Holiday', 'Later Holiday'])->assertDontSee('Other Year');
        $this->get('/holidays?year=invalid')->assertSessionHasErrors('year');
    }

    public function test_india_holidays_are_listed_with_their_2026_dates_and_weekdays(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/holidays?year=2026')->assertOk()->assertSeeInOrder([
            'Makara Sankranthi', '14 Jan 2026', 'Wednesday',
            'Republic Day', '26 Jan 2026', 'Monday',
            'Ugadi', '19 Mar 2026', 'Thursday',
            'Id-ul-Fitr', '21 Mar 2026', 'Saturday',
            'Mahavir Jayanti', '31 Mar 2026', 'Tuesday',
            'Good Friday', '03 Apr 2026', 'Friday',
            'Buddha Purnima', '01 May 2026', 'Friday',
            'Id-ul-Zuha (Bakrid)', '27 May 2026', 'Wednesday',
            'Muharram', '26 Jun 2026', 'Friday',
            'Independence Day', '15 Aug 2026', 'Saturday',
            'Milad Un Nabi or Id-E-Milad (Birthday of Prophet Mohammad)', '26 Aug 2026', 'Wednesday',
            'Ganesh Chaturthi', '14 Sep 2026', 'Monday',
            "Mahatma Gandhi's Birthday", '02 Oct 2026', 'Friday',
            'Dussehra (Vijay Dashmi)', '20 Oct 2026', 'Tuesday',
            'Diwali (Deepavali)', '08 Nov 2026', 'Sunday',
            "Guru Nanak's Birthday", '24 Nov 2026', 'Tuesday',
            'Christmas Day', '25 Dec 2026', 'Friday',
        ]);
    }

    public function test_guest_and_employee_cannot_add_holidays_and_unlinked_accounts_see_no_company_data(): void
    {
        Holiday::factory()->create(['name' => 'Private Holiday']);
        $this->get('/holidays')->assertRedirect('/login');
        $this->post('/holidays')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->post('/holidays', ['name' => 'Unauthorized', 'date' => '2026-12-01'])->assertForbidden();
        $this->get('/holidays')->assertOk()->assertDontSee('Private Holiday')->assertSee('Contact your administrator');
        $this->assertDatabaseCount('holidays', 1);
    }

    public function test_invalid_or_duplicate_holidays_are_rejected_and_names_are_escaped(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/holidays', ['name' => '', 'date' => 'invalid'])->assertSessionHasErrors(['name', 'date']);
        $this->post('/holidays', ['name' => '<script>alert(1)</script>', 'date' => '2026-12-01'])->assertSessionHasNoErrors();
        $this->post('/holidays', ['name' => 'Duplicate', 'date' => '2026-12-01'])->assertSessionHasErrors('date');
        $this->assertDatabaseCount('holidays', 1);
        $this->get('/holidays?year=2026')->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }
}
