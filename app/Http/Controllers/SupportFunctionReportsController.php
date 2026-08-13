<?php

namespace App\Http\Controllers;

use App\Models\ActivityType;
use App\Models\Semester;
use App\Models\ScheduledActivity;
use App\Models\Attendance;
use App\Models\TardinessAbsenceUndertime;
use App\Models\Member;
use App\Models\Section;
use App\Services\SystemRuleEvaluator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SupportFunctionReportsController extends Controller
{
    /**
     * Display the Support Function Reports page.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('view-support-function-reports');

        $semesters = Semester::all();
        $selectedSemesterId = (int) $request->input('semester_id', 1);
        $selectedYear = (int) $request->input('year', 2026);

        $selectedSemester = Semester::find($selectedSemesterId) ?? $semesters->first();

        // Month range & names mapping
        $monthNames = [
            1 => 'JANUARY', 2 => 'FEBRUARY', 3 => 'MARCH', 4 => 'APRIL',
            5 => 'MAY', 6 => 'JUNE', 7 => 'JULY', 8 => 'AUGUST',
            9 => 'SEPTEMBER', 10 => 'OCTOBER', 11 => 'NOVEMBER', 12 => 'DECEMBER'
        ];

        $startMonth = $selectedSemester ? $selectedSemester->month_start : 1;
        $endMonth = $selectedSemester ? $selectedSemester->month_end : 6;

        $semesterMonths = [];
        for ($m = $startMonth; $m <= $endMonth; $m++) {
            $semesterMonths[] = [
                'number' => $m,
                'name' => $monthNames[$m] ?? "Month {$m}"
            ];
        }

        // Apply Same-Department Rule scoping on members list
        $membersQuery = Member::with(['user', 'sections']);
        $membersQuery = SystemRuleEvaluator::scopeMemberQueryByDepartmentRule($membersQuery, $request->user());
        $members = $membersQuery->get();

        // Group members by Section
        $sections = Section::with(['members.user'])->get();
        $groupedSections = [];

        foreach ($sections as $sec) {
            $secMembers = $members->filter(function ($m) use ($sec) {
                return $m->sections->contains('id', $sec->id);
            })->values();

            if ($secMembers->isNotEmpty()) {
                $groupedSections[] = [
                    'id' => $sec->id,
                    'name' => $sec->name,
                    'members' => $secMembers->map(fn($m) => [
                        'id' => $m->id,
                        'name' => $m->user?->name ?? 'Unknown Member',
                        'username' => $m->user?->username ?? '',
                    ])
                ];
            }
        }

        // =========================================================
        // 1. MMP REPORT DATA (activity_type_id = 1)
        // =========================================================
        $this->ensureMmpMondaysForSemester($selectedSemesterId, $selectedYear);

        $mmpActivities = ScheduledActivity::where('activity_type_id', 1)
            ->where('semester_id', $selectedSemesterId)
            ->where('year', $selectedYear)
            ->orderBy('date', 'asc')
            ->get();

        $mmpActivityIds = $mmpActivities->pluck('id')->toArray();
        $mmpAttendances = Attendance::whereIn('scheduled_activity_id', $mmpActivityIds)->get();

        $mmpColumns = $mmpActivities->map(fn($act) => [
            'id' => $act->id,
            'name' => $act->name,
            'date' => $act->date,
            'label' => date('j-M', strtotime($act->date)),
            'month_number' => (int) date('n', strtotime($act->date)),
            'month_name' => strtoupper(date('F', strtotime($act->date))),
        ]);

        $mmpMatrix = [];
        foreach ($members as $m) {
            $memberAttendances = $mmpAttendances->where('member_id', $m->id);
            $totalPresent = 0;
            $cellValues = [];

            foreach ($mmpActivities as $act) {
                $att = $memberAttendances->where('scheduled_activity_id', $act->id)->first();
                $val = '1';
                if ($att) {
                    $val = !empty($att->status) ? $att->status : ($att->is_present ? '1' : 'A');
                }
                $cellValues[$act->id] = $val;
                if ($val === '1') {
                    $totalPresent++;
                }
            }

            $totalPossible = count($mmpActivities);
            $percentage = $totalPossible > 0 ? round(($totalPresent / $totalPossible) * 100) : 100;

            $mmpMatrix[$m->id] = [
                'cells' => $cellValues,
                'total' => $totalPresent,
                'total_possible' => $totalPossible,
                'percentage' => $percentage,
            ];
        }

        // =========================================================
        // 2. LGU ACTIVITIES REPORT DATA (activity_type_id = 2)
        // =========================================================
        $lguActivities = ScheduledActivity::where('activity_type_id', 2)
            ->where('semester_id', $selectedSemesterId)
            ->where('year', $selectedYear)
            ->orderBy('date', 'asc')
            ->get();

        $lguActivityIds = $lguActivities->pluck('id')->toArray();
        $lguAttendances = Attendance::whereIn('scheduled_activity_id', $lguActivityIds)->get();

        $lguColumns = $lguActivities->map(fn($act) => [
            'id' => $act->id,
            'name' => $act->name,
            'date' => $act->date,
        ]);

        $lguMatrix = [];
        foreach ($members as $m) {
            $memberAttendances = $lguAttendances->where('member_id', $m->id);
            $totalPresent = 0;
            $cellValues = [];

            foreach ($lguActivities as $act) {
                $att = $memberAttendances->where('scheduled_activity_id', $act->id)->first();
                $val = '1';
                if ($att) {
                    $val = !empty($att->status) ? $att->status : ($att->is_present ? '1' : 'A');
                }
                $cellValues[$act->id] = $val;
                if ($val === '1') {
                    $totalPresent++;
                }
            }

            $totalPossible = count($lguActivities);
            $percentage = $totalPossible > 0 ? round(($totalPresent / $totalPossible) * 100) : 100;

            $lguMatrix[$m->id] = [
                'cells' => $cellValues,
                'total' => $totalPresent,
                'total_possible' => $totalPossible,
                'percentage' => $percentage,
            ];
        }

        // =========================================================
        // 3. TARDY & ABSENT & UNDERTIME DATA (tardiness_absences_undertimes)
        // =========================================================
        $tardyRecords = TardinessAbsenceUndertime::where('semester_id', $selectedSemesterId)
            ->where('year', $selectedYear)
            ->get();

        $tardyMatrix = [];
        $undertimeMatrix = [];

        foreach ($members as $m) {
            $memberRecords = $tardyRecords->where('member_id', $m->id);

            $tardyMonths = [];
            $utMonths = [];
            $totalTardy = 0;
            $totalAbsent = 0;
            $totalUndertime = 0;

            foreach ($semesterMonths as $mObj) {
                $mNum = $mObj['number'];
                $rec = $memberRecords->where('month', $mNum)->first();

                $tVal = $rec ? (string) $rec->tardy : '0';
                $aVal = $rec ? (string) $rec->absences : '0';
                $utVal = $rec ? (string) $rec->undertime : '0';

                if ($tVal !== 'ON-LEAVE' && is_numeric($tVal)) {
                    $totalTardy += (int) $tVal;
                }
                if ($aVal !== 'ON-LEAVE' && is_numeric($aVal)) {
                    $totalAbsent += (int) $aVal;
                }
                if ($utVal !== 'ON-LEAVE' && is_numeric($utVal)) {
                    $totalUndertime += (int) $utVal;
                }

                $tardyMonths[$mNum] = ['tardy' => $tVal, 'absences' => $aVal];
                $utMonths[$mNum] = $utVal;
            }

            $tardyMatrix[$m->id] = [
                'months' => $tardyMonths,
                'total_tardy' => $totalTardy,
                'total_absent' => $totalAbsent,
            ];

            $undertimeMatrix[$m->id] = [
                'months' => $utMonths,
                'total_undertime' => $totalUndertime,
            ];
        }

        $canEdit = SystemRuleEvaluator::canEditSupportFunctionReports($request->user());

        return Inertia::render('Reports/SupportFunctionReports', [
            'canEdit' => $canEdit,
            'semesters' => $semesters,
            'selectedSemesterId' => $selectedSemesterId,
            'selectedYear' => $selectedYear,
            'selectedSemester' => $selectedSemester,
            'semesterMonths' => $semesterMonths,
            'groupedSections' => $groupedSections,
            'mmpColumns' => $mmpColumns,
            'mmpMatrix' => $mmpMatrix,
            'lguColumns' => $lguColumns,
            'lguMatrix' => $lguMatrix,
            'tardyMatrix' => $tardyMatrix,
            'undertimeMatrix' => $undertimeMatrix,
        ]);
    }

    /**
     * Update attendance record status (1, A, ON-LEAVE).
     */
    public function updateAttendance(Request $request)
    {
        Gate::authorize('edit-support-function-reports');

        $request->validate([
            'member_id' => 'required|exists:members,id',
            'scheduled_activity_id' => 'required|exists:scheduled_activities,id',
            'status' => 'required|in:1,A,ON-LEAVE',
        ]);

        Attendance::updateOrCreate(
            [
                'member_id' => $request->member_id,
                'scheduled_activity_id' => $request->scheduled_activity_id,
            ],
            [
                'status' => $request->status,
                'is_present' => $request->status === '1',
            ]
        );

        return back()->with('success', 'Attendance status updated successfully.');
    }

    /**
     * Update tardiness, absences, or undertime value (numeric or ON-LEAVE).
     */
    public function updateTardinessAbsenceUndertime(Request $request)
    {
        Gate::authorize('edit-support-function-reports');

        $request->validate([
            'member_id' => 'required|exists:members,id',
            'month' => 'required|integer',
            'year' => 'required|integer',
            'semester_id' => 'required|exists:semesters,id',
            'field' => 'required|in:tardy,absences,undertime',
            'value' => 'required|string',
        ]);

        $rec = TardinessAbsenceUndertime::firstOrNew([
            'member_id' => $request->member_id,
            'month' => $request->month,
            'year' => $request->year,
            'semester_id' => $request->semester_id,
        ]);

        $field = $request->field;
        $rec->$field = $request->value;
        $rec->save();

        return back()->with('with', 'Record updated successfully.');
    }

    /**
     * Store a new scheduled activity (e.g. LGU activity).
     */
    public function storeActivity(Request $request)
    {
        Gate::authorize('edit-support-function-reports');

        $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'semester_id' => 'required|exists:semesters,id',
            'year' => 'required|integer',
            'activity_type_id' => 'nullable|exists:activity_types,id',
        ]);

        ScheduledActivity::create([
            'name' => $request->name,
            'date' => $request->date,
            'semester_id' => $request->semester_id,
            'year' => $request->year,
            'activity_type_id' => $request->activity_type_id ?? 2,
        ]);

        return back()->with('success', 'Activity created successfully.');
    }

    /**
     * Update an existing scheduled activity.
     */
    public function updateActivity(Request $request, ScheduledActivity $scheduledActivity)
    {
        Gate::authorize('edit-support-function-reports');

        $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date',
        ]);

        $scheduledActivity->update([
            'name' => $request->name,
            'date' => $request->date,
        ]);

        return back()->with('success', 'Activity updated successfully.');
    }

    /**
     * Delete a scheduled activity.
     */
    public function destroyActivity(ScheduledActivity $scheduledActivity)
    {
        Gate::authorize('edit-support-function-reports');

        $scheduledActivity->delete();

        return back()->with('success', 'Activity deleted successfully.');
    }

    /**
     * Ensure all Mondays for a semester are automatically generated if no MMP activities exist yet.
     */
    private function ensureMmpMondaysForSemester(int $semesterId, int $year): void
    {
        $semester = Semester::find($semesterId);
        if (!$semester) return;

        $existingCount = ScheduledActivity::where('activity_type_id', 1)
            ->where('semester_id', $semesterId)
            ->where('year', $year)
            ->count();

        if ($existingCount === 0) {
            $startMonth = $semester->month_start;
            $endMonth = $semester->month_end;

            $startDate = \Carbon\Carbon::createFromDate($year, $startMonth, 1)->startOfDay();
            $endDate = \Carbon\Carbon::createFromDate($year, $endMonth, 1)->endOfMonth()->endOfDay();

            $curr = $startDate->copy();
            if (!$curr->isMonday()) {
                $curr->modify('next monday');
            }

            while ($curr->lte($endDate)) {
                $dateStr = $curr->format('Y-m-d');
                ScheduledActivity::create([
                    'activity_type_id' => 1,
                    'name' => 'MMP Flag Raising ' . $dateStr,
                    'date' => $dateStr,
                    'semester_id' => $semesterId,
                    'year' => $year,
                ]);

                $curr->addWeek();
            }
        }
    }
}
