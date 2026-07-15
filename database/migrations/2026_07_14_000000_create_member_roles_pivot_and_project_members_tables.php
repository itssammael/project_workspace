<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create member_member_role pivot table
        Schema::create('member_member_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->foreignId('member_role_id')->constrained('member_roles')->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['member_id', 'member_role_id']);
        });

        // 2. Migrate existing singular roles to pivot table
        $members = DB::table('members')->get();
        foreach ($members as $member) {
            if ($member->member_role_id) {
                DB::table('member_member_role')->insert([
                    'member_id' => $member->id,
                    'member_role_id' => $member->member_role_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 3. Drop member_role_id foreign key and column from members table
        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign(['member_role_id']);
            $table->dropColumn('member_role_id');
        });

        // 4. Create project_members table
        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->foreignId('member_role_id')->nullable()->constrained('member_roles')->onDelete('set null');
            $table->timestamps();

            $table->unique(['project_id', 'member_id']);
        });

        // 5. Populate project_members for existing projects based on team assignments
        $projects = DB::table('projects')->whereNotNull('team_id')->get();
        foreach ($projects as $project) {
            $teamMembers = DB::table('teams_member_pivot')
                ->where('team_id', $project->team_id)
                ->get();

            foreach ($teamMembers as $tm) {
                // Find a role for this member
                $roleId = DB::table('member_member_role')
                    ->where('member_id', $tm->member_id)
                    ->value('member_role_id');

                DB::table('project_members')->insert([
                    'project_id' => $project->id,
                    'member_id' => $tm->member_id,
                    'member_role_id' => $roleId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Re-add member_role_id to members
        Schema::table('members', function (Blueprint $table) {
            $table->foreignId('member_role_id')->nullable()->constrained('member_roles')->onDelete('set null');
        });

        // Restore singular roles from pivot
        $pivotRoles = DB::table('member_member_role')->get();
        foreach ($pivotRoles as $pr) {
            DB::table('members')
                ->where('id', $pr->member_id)
                ->update(['member_role_id' => $pr->member_role_id]);
        }

        Schema::dropIfExists('project_members');
        Schema::dropIfExists('member_member_role');
    }
};
