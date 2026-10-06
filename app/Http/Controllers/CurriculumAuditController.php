<?php

namespace App\Http\Controllers;

use App\Models\CurriculumAudit;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CurriculumAuditController extends Controller
{
    public function index(Request $request): View
    {
        $types = ['program', 'course', 'plo', 'program_course', 'plo_matrix', 'program_version'];
        $query = CurriculumAudit::with('actor')->latest();
        if (in_array($request->input('type'), $types, true)) {
            $query->where('subject_type', $request->input('type'));
        }
        if ($request->filled('program_id')) {
            $query->where('program_id', $request->integer('program_id'));
        }

        return view('programs.audit', [
            'audits' => $query->paginate(30)->withQueryString(),
            'programs' => Program::orderBy('name_vi')->get(['id', 'name_vi']),
        ]);
    }
}
