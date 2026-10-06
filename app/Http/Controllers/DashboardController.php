<?php

namespace App\Http\Controllers;

use App\Models\AcademicImport;
use App\Models\IsoWorkflow;
use App\Models\Program;
use App\Models\Student;
use App\Models\WarningLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $students = Student::query()
            ->when($request->filled('program_id'), fn ($query) => $query->where('program_id', $request->input('program_id')))
            ->when($request->filled('degree_level'), fn ($query) => $query->where('degree_level', $request->input('degree_level')))
            ->when($request->filled('year'), fn ($query) => $query->where('enrollment_year', $request->input('year')));
        $today = today();
        $graduationCohort = (clone $students)->whereDate('expected_graduation_date', '<=', $today);
        $graduationEligible = (clone $graduationCohort)->count();
        $graduatedOnTime = (clone $graduationCohort)
            ->where('status', 'graduated')
            ->whereNotNull('graduated_at')
            ->whereColumn('graduated_at', '<=', 'expected_graduation_date')
            ->count();

        $programs = Program::where('is_active', true)->orderBy('name_vi')->get();
        $programCount = Program::where('is_active', true)
            ->when($request->filled('program_id'), fn ($query) => $query->whereKey($request->input('program_id')))
            ->when($request->filled('degree_level'), fn ($query) => $query->where('degree_level', $request->input('degree_level')))
            ->count();

        return view('dashboard', [
            'programs' => $programs,
            'stats' => [
                'students' => (clone $students)->count(),
                'warnings_open' => WarningLog::where('status', 'open')->whereIn('student_id', (clone $students)->select('id'))->count(),
                'iso_overdue' => IsoWorkflow::where('status', 'in_progress')->whereHas('steps', fn ($query) => $query->whereDate('deadline', '<', $today)->where('status', '!=', 'completed'))->count(),
                'programs' => $programCount,
                'import_errors' => AcademicImport::where(fn ($query) => $query->where('status', 'failed')->orWhere('error_count', '>', 0))->count(),
                'graduation_rate' => $graduationEligible ? round($graduatedOnTime / $graduationEligible * 100, 1) : null,
                'graduation_eligible' => $graduationEligible,
                'graduated_on_time' => $graduatedOnTime,
            ],
        ]);
    }
}
