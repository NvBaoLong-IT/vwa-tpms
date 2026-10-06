<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\ProgramVersion;
use App\Services\ProgramCreditReconciler;
use App\Services\ProgramVersionService;
use App\Services\ProgramWordExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ProgramVersionController extends Controller
{
    public function store(Request $request, Program $program, ProgramVersionService $versions): RedirectResponse
    {
        $data = $request->validate([
            'cohort_year' => ['required', 'integer', 'between:1900,2100'],
            'decision_number' => ['required', 'string', 'max:100'],
            'decision_date' => ['required', 'date'],
        ]);
        $version = $versions->capture($program, (int) $data['cohort_year'], $data['decision_number'], $data['decision_date'], $request->user()->id);

        return redirect()->route('programs.versions.show', [$program, $version])->with('success', 'Đã lưu bản chụp chương trình đào tạo.');
    }

    public function show(Program $program, ProgramVersion $version, ProgramCreditReconciler $reconciler): View
    {
        abort_unless($version->program_id === $program->id, 404);

        return view('programs.version', ['program' => $program, 'version' => $version, 'reconciliation' => $reconciler->forVersion($version)]);
    }

    public function publish(Program $program, ProgramVersion $version, ProgramVersionService $versions): RedirectResponse
    {
        abort_unless($version->program_id === $program->id, 404);
        $versions->publish($version);

        return back()->with('success', 'Đã công bố phiên bản cho khóa '.$version->cohort_year.'.');
    }

    public function exportWord(Program $program, ProgramVersion $version, ProgramWordExporter $exporter): Response
    {
        abort_unless($version->program_id === $program->id, 404);
        $code = preg_replace('/[^A-Za-z0-9_-]/', '-', $version->snapshot['program']['code']);
        $filename = 'CTDT-'.$code.'-K'.$version->cohort_year.'-lan-'.$version->revision.'.docx';

        return response($exporter->render($version), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
