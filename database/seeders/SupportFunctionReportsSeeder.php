<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ActivityType;
use App\Models\Semester;
use App\Models\ScheduledActivity;
use App\Models\Attendance;
use App\Models\TardinessAbsenceUndertime;
use App\Models\Member;
use App\Models\User;

class SupportFunctionReportsSeeder extends Seeder
{
    /**
     * Run the database seeds for 2026 First Semester reports matching reference images.
     */
    public function run(): void
    {
        $mmpType = ActivityType::firstOrCreate(['id' => 1], ['name' => 'MMP']);
        $lguType = ActivityType::firstOrCreate(['id' => 2], ['name' => 'LGU Activities']);

        $sem1 = Semester::firstOrCreate(
            ['id' => 1],
            ['name' => 'First Semester', 'month_start' => 1, 'month_end' => 6]
        );

        $year = 2026;

        // Map member names to Member models
        $memberMap = [];
        foreach (Member::with('user')->get() as $m) {
            if ($m->user) {
                $memberMap[$m->user->name] = $m;
            }
        }

        // =========================================================
        // 1. SEED SCHEDULING & ATTENDANCE FOR MMP (Monday Morning)
        // =========================================================
        $mmpDates = [
            '2026-01-05', '2026-01-12', '2026-01-19', '2026-01-26',
            '2026-02-02', '2026-02-23',
            '2026-03-02', '2026-03-09', '2026-03-16', '2026-03-23',
            '2026-04-06', '2026-04-13', '2026-04-20',
            '2026-05-04', '2026-05-18', '2026-05-25',
            '2026-06-01', '2026-06-15', '2026-06-22', '2026-06-29',
        ];

        $mmpActivities = [];
        foreach ($mmpDates as $dStr) {
            $act = ScheduledActivity::firstOrCreate(
                [
                    'activity_type_id' => $mmpType->id,
                    'date' => $dStr,
                    'semester_id' => $sem1->id,
                    'year' => $year,
                ],
                [
                    'name' => 'MMP Flag Raising ' . $dStr,
                ]
            );
            $mmpActivities[$dStr] = $act;
        }

        // Absent dates mapping for MMP
        $mmpAbsences = [
            'ELNAR, MC.' => ['2026-04-20'],
            'NAMA, M.' => ['2026-01-05', '2026-03-23'],
            'ENCABO, D.' => ['2026-03-09'],
            'ARNAIZ, EG.' => ['2026-02-23'],
            'ALCAZAR, N.' => ['2026-05-18', '2026-06-22'],
            'CALDA, J.' => ['2026-02-23', '2026-05-25', '2026-06-01', '2026-06-22'],
            'BOLLOS, LH.' => ['2026-02-23'],
            'TABUNDA, AP.' => ['2026-01-12', '2026-01-19', '2026-05-18', '2026-05-25', '2026-06-22'],
        ];

        foreach ($memberMap as $name => $member) {
            $absentDates = $mmpAbsences[$name] ?? [];
            foreach ($mmpActivities as $dStr => $act) {
                Attendance::updateOrCreate(
                    [
                        'member_id' => $member->id,
                        'scheduled_activity_id' => $act->id,
                    ],
                    [
                        'is_present' => !in_array($dStr, $absentDates),
                    ]
                );
            }
        }

        // =========================================================
        // 2. SEED SCHEDULING & ATTENDANCE FOR LGU ACTIVITIES
        // =========================================================
        $lguList = [
            ['name' => 'INTERFAITH SPIRITUAL SERVICE (Feb. 9, 2026)', 'date' => '2026-02-09'],
            ['name' => 'OPENING SALVO/TAWO-TAWO (FEB. 9, 2026)', 'date' => '2026-02-09'],
            ['name' => 'TAWO-TAWO CIVIC PARADE-FEB. 17, 2026', 'date' => '2026-02-17'],
            ['name' => 'TAWO-TAWO SHOWDOWN (FEB. 16, 2026)', 'date' => '2026-02-16'],
            ['name' => 'BAYA WAVE Feb. 28, 2026', 'date' => '2026-02-28'],
            ['name' => 'INDEPENDENCE DAY- JUNE 12, 2026', 'date' => '2026-06-12'],
        ];

        $lguActivities = [];
        foreach ($lguList as $item) {
            $act = ScheduledActivity::firstOrCreate(
                [
                    'activity_type_id' => $lguType->id,
                    'name' => $item['name'],
                    'semester_id' => $sem1->id,
                    'year' => $year,
                ],
                [
                    'date' => $item['date'],
                ]
            );
            $lguActivities[$item['name']] = $act;
        }

        // Absences for LGU Activities
        $lguAbsences = [
            'ALICANTE, S.' => ['TAWO-TAWO SHOWDOWN (FEB. 16, 2026)'],
            'LAMPAGO, RM.' => ['TAWO-TAWO SHOWDOWN (FEB. 16, 2026)'],
            'PINANONANG, R.' => ['TAWO-TAWO SHOWDOWN (FEB. 16, 2026)', 'BAYA WAVE Feb. 28, 2026'],
            'ELNAR, MC.' => ['TAWO-TAWO SHOWDOWN (FEB. 16, 2026)'],
            'AGUILAR, L W.' => ['TAWO-TAWO CIVIC PARADE-FEB. 17, 2026', 'TAWO-TAWO SHOWDOWN (FEB. 16, 2026)'],
            'NAMA, M.' => ['TAWO-TAWO SHOWDOWN (FEB. 16, 2026)', 'BAYA WAVE Feb. 28, 2026'],
            'ENCABO, D.' => ['TAWO-TAWO SHOWDOWN (FEB. 16, 2026)'],
            'ARNAIZ, EG.' => ['INTERFAITH SPIRITUAL SERVICE (Feb. 9, 2026)', 'OPENING SALVO/TAWO-TAWO (FEB. 9, 2026)', 'TAWO-TAWO CIVIC PARADE-FEB. 17, 2026', 'TAWO-TAWO SHOWDOWN (FEB. 16, 2026)', 'BAYA WAVE Feb. 28, 2026'],
            'MENDIOLA, NR.' => ['BAYA WAVE Feb. 28, 2026'],
            'ARQUIO, JR.' => ['TAWO-TAWO CIVIC PARADE-FEB. 17, 2026', 'TAWO-TAWO SHOWDOWN (FEB. 16, 2026)'],
            'ALCAZAR, N.' => ['TAWO-TAWO SHOWDOWN (FEB. 16, 2026)', 'BAYA WAVE Feb. 28, 2026'],
            'CALDA, J.' => ['TAWO-TAWO CIVIC PARADE-FEB. 17, 2026', 'TAWO-TAWO SHOWDOWN (FEB. 16, 2026)', 'BAYA WAVE Feb. 28, 2026', 'INDEPENDENCE DAY- JUNE 12, 2026'],
            'BELINGAN, K.' => ['TAWO-TAWO CIVIC PARADE-FEB. 17, 2026'],
            'AMORES, MC.' => ['BAYA WAVE Feb. 28, 2026'],
            'BOLLOS, LH.' => ['TAWO-TAWO SHOWDOWN (FEB. 16, 2026)', 'INDEPENDENCE DAY- JUNE 12, 2026'],
            'TABUNDA, AP.' => ['TAWO-TAWO SHOWDOWN (FEB. 16, 2026)'],
        ];

        foreach ($memberMap as $name => $member) {
            $absentActs = $lguAbsences[$name] ?? [];
            foreach ($lguActivities as $actName => $act) {
                Attendance::updateOrCreate(
                    [
                        'member_id' => $member->id,
                        'scheduled_activity_id' => $act->id,
                    ],
                    [
                        'is_present' => !in_array($actName, $absentActs),
                    ]
                );
            }
        }

        // =========================================================
        // 3. SEED TARDINESS, ABSENCES & UNDERTIMES (Jan-June 2026)
        // =========================================================
        $tardyAbsenceData = [
            'ALICANTE, S.' => [
                1 => ['tardy' => 1, 'absences' => 0, 'ut' => 1],
                2 => ['tardy' => 1, 'absences' => 0, 'ut' => 0],
                3 => ['tardy' => 2, 'absences' => 0, 'ut' => 0],
                4 => ['tardy' => 2, 'absences' => 1, 'ut' => 0],
                5 => ['tardy' => 2, 'absences' => 0, 'ut' => 0],
                6 => ['tardy' => 1, 'absences' => 2, 'ut' => 0],
            ],
            'LAMPAGO, RM.' => [
                1 => ['tardy' => 0, 'absences' => 0, 'ut' => 1],
                2 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                3 => ['tardy' => 0, 'absences' => 0, 'ut' => 1],
                4 => ['tardy' => 0, 'absences' => 0, 'ut' => 1],
                5 => ['tardy' => 0, 'absences' => 0, 'ut' => 2],
                6 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
            ],
            'ELNAR, MC.' => [
                1 => ['tardy' => 1, 'absences' => 0, 'ut' => 1],
                2 => ['tardy' => 0, 'absences' => 0, 'ut' => 1],
                3 => ['tardy' => 3, 'absences' => 0, 'ut' => 1],
                4 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                5 => ['tardy' => 3, 'absences' => 0, 'ut' => 1],
                6 => ['tardy' => 2, 'absences' => 0, 'ut' => 0],
            ],
            'ZERNA, E.' => [
                1 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                2 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                3 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                4 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                5 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                6 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
            ],
            'PINANONANG, R.' => [
                1 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                2 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                3 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                4 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                5 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                6 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
            ],
            'AGUILAR, L W.' => [
                1 => ['tardy' => 5, 'absences' => 0, 'ut' => 1],
                2 => ['tardy' => 5, 'absences' => 1, 'ut' => 0],
                3 => ['tardy' => 6, 'absences' => 2, 'ut' => 0],
                4 => ['tardy' => 5, 'absences' => 1, 'ut' => 0],
                5 => ['tardy' => 5, 'absences' => 0, 'ut' => 1],
                6 => ['tardy' => 5, 'absences' => 0, 'ut' => 0],
            ],
            'NAMA, M.' => [
                1 => ['tardy' => 2, 'absences' => 3, 'ut' => 0],
                2 => ['tardy' => 8, 'absences' => 1, 'ut' => 0],
                3 => ['tardy' => 7, 'absences' => 1, 'ut' => 0],
                4 => ['tardy' => 4, 'absences' => 0, 'ut' => 0],
                5 => ['tardy' => 6, 'absences' => 0, 'ut' => 0],
                6 => ['tardy' => 4, 'absences' => 3, 'ut' => 1],
            ],
            'ENCABO, D.' => [
                1 => ['tardy' => 4, 'absences' => 0, 'ut' => 0],
                2 => ['tardy' => 3, 'absences' => 0, 'ut' => 0],
                3 => ['tardy' => 1, 'absences' => 1, 'ut' => 0],
                4 => ['tardy' => 2, 'absences' => 0, 'ut' => 0],
                5 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                6 => ['tardy' => 4, 'absences' => 1, 'ut' => 0],
            ],
            'ARNAIZ, EG.' => [
                1 => ['tardy' => 5, 'absences' => 1, 'ut' => 0],
                2 => ['tardy' => 0, 'absences' => 17, 'ut' => 0],
                3 => ['tardy' => 4, 'absences' => 0, 'ut' => 0],
                4 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                5 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                6 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
            ],
            'PINILI, A.' => [
                1 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                2 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                3 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                4 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                5 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                6 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
            ],
            'MENDIOLA, NR.' => [
                1 => ['tardy' => 0, 'absences' => 0, 'ut' => 1],
                2 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                3 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                4 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                5 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                6 => ['tardy' => 0, 'absences' => 0, 'ut' => 2],
            ],
            'BELINGAN, K.' => [
                1 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                2 => ['tardy' => 0, 'absences' => 0, 'ut' => 2],
                3 => ['tardy' => 0, 'absences' => 0, 'ut' => 1],
                4 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                5 => ['tardy' => 0, 'absences' => 0, 'ut' => 1],
                6 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
            ],
            'AMORES, MC.' => [
                1 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                2 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                3 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                4 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                5 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                6 => ['tardy' => 0, 'absences' => 1, 'ut' => 0],
            ],
            'ARQUIO, JR.' => [
                1 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                2 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                3 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                4 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                5 => ['tardy' => 1, 'absences' => 0, 'ut' => 0],
                6 => ['tardy' => 1, 'absences' => 0, 'ut' => 0],
            ],
            'CALDA, J.' => [
                1 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                2 => ['tardy' => 1, 'absences' => 1, 'ut' => 1],
                3 => ['tardy' => 1, 'absences' => 1, 'ut' => 2],
                4 => ['tardy' => 0, 'absences' => 1, 'ut' => 1],
                5 => ['tardy' => 3, 'absences' => 0, 'ut' => 0],
                6 => ['tardy' => 2, 'absences' => 3, 'ut' => 2],
            ],
            'ALCAZAR, N.' => [
                1 => ['tardy' => 0, 'absences' => 0, 'ut' => 1],
                2 => ['tardy' => 0, 'absences' => 0, 'ut' => 3],
                3 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                4 => ['tardy' => 0, 'absences' => 0, 'ut' => 1],
                5 => ['tardy' => 0, 'absences' => 1, 'ut' => 0],
                6 => ['tardy' => 0, 'absences' => 4, 'ut' => 0],
            ],
            'ENTAC, ML.' => [
                1 => ['tardy' => 3, 'absences' => 0, 'ut' => 0],
                2 => ['tardy' => 1, 'absences' => 0, 'ut' => 0],
                3 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                4 => ['tardy' => 0, 'absences' => 0, 'ut' => 1],
                5 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                6 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
            ],
            'BOLLOS, LH.' => [
                1 => ['tardy' => 0, 'absences' => 0, 'ut' => 0],
                2 => ['tardy' => 1, 'absences' => 0, 'ut' => 0],
                3 => ['tardy' => 2, 'absences' => 0, 'ut' => 0],
                4 => ['tardy' => 4, 'absences' => 0, 'ut' => 0],
                5 => ['tardy' => 5, 'absences' => 0, 'ut' => 0],
                6 => ['tardy' => 2, 'absences' => 0, 'ut' => 1],
            ],
            'TABUNDA, AP.' => [
                1 => ['tardy' => 3, 'absences' => 1, 'ut' => 0],
                2 => ['tardy' => 2, 'absences' => 0, 'ut' => 0],
                3 => ['tardy' => 1, 'absences' => 0, 'ut' => 2],
                4 => ['tardy' => 0, 'absences' => 1, 'ut' => 0],
                5 => ['tardy' => 1, 'absences' => 1, 'ut' => 0],
                6 => ['tardy' => 3, 'absences' => 2, 'ut' => 0],
            ],
        ];

        foreach ($tardyAbsenceData as $name => $monthsData) {
            if (!isset($memberMap[$name])) {
                continue;
            }
            $member = $memberMap[$name];

            foreach ($monthsData as $mNum => $vals) {
                TardinessAbsenceUndertime::updateOrCreate(
                    [
                        'member_id' => $member->id,
                        'month' => $mNum,
                        'year' => $year,
                        'semester_id' => $sem1->id,
                    ],
                    [
                        'tardy' => $vals['tardy'],
                        'absences' => $vals['absences'],
                        'undertime' => $vals['ut'],
                    ]
                );
            }
        }
    }
}
