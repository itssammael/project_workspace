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
                if ($m->user && $m->user->name === 'MARINDA, L.') {
                    $cellValues[$act->id] = 'ON-LEAVE';
                } else {
                    $att = $memberAttendances->where('scheduled_activity_id', $act->id)->first();
                    $isPresent = $att ? $att->is_present : true;
                    if ($isPresent) {
                        $totalPresent++;
                        $cellValues[$act->id] = '1';
                    } else {
                        $cellValues[$act->id] = 'A';
                    }
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
                if ($m->user && $m->user->name === 'MARINDA, L.') {
                    $cellValues[$act->id] = 'ON-LEAVE';
                } else {
                    $att = $memberAttendances->where('scheduled_activity_id', $act->id)->first();
                    $isPresent = $att ? $att->is_present : true;
                    if ($isPresent) {
                        $totalPresent++;
                        $cellValues[$act->id] = '1';
                    } else {
                        $cellValues[$act->id] = 'A';
                    }
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

                if ($m->user && $m->user->name === 'MARINDA, L.') {
                    $tardyMonths[$mNum] = ['tardy' => 'ON-LEAVE', 'absences' => 'ON-LEAVE'];
                    $utMonths[$mNum] = 'ON-LEAVE';
                } else {
                    $tVal = $rec ? (int) $rec->tardy : 0;
                    $aVal = $rec ? (int) $rec->absences : 0;
                    $utVal = $rec ? (int) $rec->undertime : 0;

                    $totalTardy += $tVal;
                    $totalAbsent += $aVal;
                    $totalUndertime += $utVal;

                    $tardyMonths[$mNum] = ['tardy' => $tVal, 'absences' => $aVal];
                    $utMonths[$mNum] = $utVal;
                }
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

        return Inertia::render('Reports/SupportFunctionReports', [
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
}
