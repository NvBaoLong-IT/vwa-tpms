<?php

namespace App\Http\Controllers;

use App\Models\IsoWorkflow;
use App\Models\Program;
use App\Models\ProgramVersion;
use App\Services\CurriculumSnapshotDiffer;
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

    public function show(Request $request, Program $program, ProgramVersion $version, ProgramCreditReconciler $reconciler, CurriculumSnapshotDiffer $differ): View
    {
        abort_unless($version->program_id === $program->id, 404);

        $request->validate(['compare' => ['nullable', 'integer']]);
        $versions = $program->versions()->whereKeyNot($version->id)->orderByDesc('cohort_year')->orderByDesc('revision')->get();
        $comparison = $request->filled('compare') ? $program->versions()->findOrFail($request->integer('compare')) : null;
        $version->load(['reviewer', 'isoWorkflow']);
        $changes = $comparison ? $differ->compare(
            $comparison->snapshot + ['version' => $this->comparisonMetadata($comparison)],
            $version->snapshot + ['version' => $this->comparisonMetadata($version)],
        ) : [];

        return view('programs.version', ['program' => $program, 'version' => $version, 'reconciliation' => $reconciler->forVersion($version), 'versions' => $versions, 'comparison' => $comparison, 'changes' => $changes, 'workflows' => IsoWorkflow::where('program_id', $program->id)->where('status', '!=', 'cancelled')->orderByDesc('id')->get()]);
    }

    private function comparisonMetadata(ProgramVersion $version): array
    {
        return [
            'cohort_year' => $version->cohort_year,
            'revision' => $version->revision,
            'decision_number' => $version->decision_number,
            'decision_date' => $version->decision_date?->format('d/m/Y'),
            'effective_from' => $version->effective_from?->format('d/m/Y'),
            'effective_until' => $version->effective_until?->format('d/m/Y'),
        ];
    }

    public function submit(Request $request, Program $program, ProgramVersion $version, ProgramVersionService $versions): RedirectResponse
    {
        abort_unless($version->program_id === $program->id, 404);
        $data = $request->validate([
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'iso_workflow_id' => ['nullable', 'integer', 'exists:iso_workflows,id'],
        ]);
        $versions->submit($version, array_merge(['effective_until' => null, 'iso_workflow_id' => null], $data), $request->user()->id);

        return back()->with('success', 'Đã trình duyệt phiên bản CTĐT. Nội dung bản lưu được giữ nguyên.');
    }

    public function review(Request $request, Program $program, ProgramVersion $version, ProgramVersionService $versions): RedirectResponse
    {
        abort_unless($version->program_id === $program->id, 404);
        $data = $request->validate(['decision' => ['required', 'in:approve,return'], 'review_note' => ['required', 'string', 'max:4000']]);
        $versions->review($version, $data['decision'] === 'approve', $data['review_note'], $request->user()->id);

        return back()->with('success', $data['decision'] === 'approve' ? 'Đã phê duyệt phiên bản CTĐT.' : 'Đã trả về để bổ sung.');
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
