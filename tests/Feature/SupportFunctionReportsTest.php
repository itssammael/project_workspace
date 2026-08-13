<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Member;
use App\Models\ScheduledActivity;
use App\Models\Attendance;
use App\Models\TardinessAbsenceUndertime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportFunctionReportsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    /**
     * Test authorized user can view support function reports.
     */
    public function test_user_can_view_support_function_reports(): void
    {
        $user = User::first();
        $response = $this->actingAs($user)->get(route('support-function-reports.index'));
        $response->assertStatus(200);
    }

    /**
     * Test updating attendance status (1, A, ON-LEAVE).
     */
    public function test_can_update_attendance_status(): void
    {
        $user = User::first();
        $member = Member::first();
        $activity = ScheduledActivity::first();

        $response = $this->actingAs($user)->post(route('support-function-reports.update-attendance'), [
            'member_id' => $member->id,
            'scheduled_activity_id' => $activity->id,
            'status' => 'ON-LEAVE',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('attendances', [
            'member_id' => $member->id,
            'scheduled_activity_id' => $activity->id,
            'status' => 'ON-LEAVE',
            'is_present' => false,
        ]);

        // Change status to 1
        $this->actingAs($user)->post(route('support-function-reports.update-attendance'), [
            'member_id' => $member->id,
            'scheduled_activity_id' => $activity->id,
            'status' => '1',
        ]);

        $this->assertDatabaseHas('attendances', [
            'member_id' => $member->id,
            'scheduled_activity_id' => $activity->id,
            'status' => '1',
            'is_present' => true,
        ]);
    }

    /**
     * Test updating tardiness, absences, and undertime values.
     */
    public function test_can_update_tardiness_absences_and_undertime(): void
    {
        $user = User::first();
        $member = Member::first();

        // Update tardy
        $response = $this->actingAs($user)->post(route('support-function-reports.update-tardiness-undertime'), [
            'member_id' => $member->id,
            'month' => 1,
            'year' => 2026,
            'semester_id' => 1,
            'field' => 'tardy',
            'value' => '5',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tardiness_absences_undertimes', [
            'member_id' => $member->id,
            'month' => 1,
            'year' => 2026,
            'semester_id' => 1,
            'tardy' => '5',
        ]);

        // Update absences to ON-LEAVE
        $this->actingAs($user)->post(route('support-function-reports.update-tardiness-undertime'), [
            'member_id' => $member->id,
            'month' => 1,
            'year' => 2026,
            'semester_id' => 1,
            'field' => 'absences',
            'value' => 'ON-LEAVE',
        ]);

        $this->assertDatabaseHas('tardiness_absences_undertimes', [
            'member_id' => $member->id,
            'month' => 1,
            'year' => 2026,
            'semester_id' => 1,
            'absences' => 'ON-LEAVE',
        ]);
    }

    /**
     * Test creating, updating, and deleting an LGU activity.
     */
    public function test_can_add_edit_and_delete_lgu_activity(): void
    {
        $user = User::first();

        // 1. Create Activity
        $response = $this->actingAs($user)->post(route('support-function-reports.activities.store'), [
            'name' => 'NEW CIVIC RALLY',
            'date' => '2026-05-20',
            'semester_id' => 1,
            'year' => 2026,
            'activity_type_id' => 2,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('scheduled_activities', [
            'name' => 'NEW CIVIC RALLY',
            'date' => '2026-05-20',
            'activity_type_id' => 2,
        ]);

        $activity = ScheduledActivity::where('name', 'NEW CIVIC RALLY')->first();

        // 2. Update Activity
        $response = $this->actingAs($user)->put(route('support-function-reports.activities.update', $activity->id), [
            'name' => 'UPDATED CIVIC RALLY',
            'date' => '2026-05-21',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('scheduled_activities', [
            'id' => $activity->id,
            'name' => 'UPDATED CIVIC RALLY',
            'date' => '2026-05-21',
        ]);

        // 3. Delete Activity
        $response = $this->actingAs($user)->delete(route('support-function-reports.activities.destroy', $activity->id));

        $response->assertRedirect();
        $this->assertDatabaseMissing('scheduled_activities', [
            'id' => $activity->id,
        ]);
    }

    /**
     * Test MMP Mondays are automatically generated when viewing a semester with 0 MMP sessions.
     */
    public function test_mmp_mondays_are_automatically_generated_for_semester(): void
    {
        $user = User::first();

        // Ensure 0 MMP activities exist for Semester 2 (Second Semester 2026)
        ScheduledActivity::where('activity_type_id', 1)->where('semester_id', 2)->where('year', 2026)->delete();

        $response = $this->actingAs($user)->get(route('support-function-reports.index', [
            'semester_id' => 2,
            'year' => 2026,
        ]));

        $response->assertStatus(200);

        // Verify Mondays from July to December 2026 were generated
        $mmpCount = ScheduledActivity::where('activity_type_id', 1)->where('semester_id', 2)->where('year', 2026)->count();
        $this->assertGreaterThan(0, $mmpCount);
    }

    /**
     * Test non-Admin section member has view access but is denied edit access.
     */
    public function test_non_admin_section_member_has_view_access_but_is_denied_edit_access(): void
    {
        $user = User::whereHas('member.sections', function ($q) {
            $q->where('name', '!=', 'Admin');
        })->whereDoesntHave('role', function ($q) {
            $q->where('slug', 'admin');
        })->first();

        if (!$user) {
            $this->markTestSkipped('Non-admin section user not found.');
        }

        // View access should be granted (200 OK)
        $response = $this->actingAs($user)->get(route('support-function-reports.index'));
        $response->assertStatus(200);

        // Edit access should be denied (403 Forbidden)
        $member = Member::first();
        $activity = ScheduledActivity::first();

        $editResponse = $this->actingAs($user)->post(route('support-function-reports.update-attendance'), [
            'member_id' => $member->id,
            'scheduled_activity_id' => $activity->id,
            'status' => 'A',
        ]);

        $editResponse->assertStatus(403);
    }
}
