<?php

namespace App\Http\Controllers;

use App\Models\IsoFile;
use App\Models\IsoHistory;
use App\Models\IsoStep;
use App\Models\IsoWorkflow;
use App\Models\Program;
use App\Models\User;
use App\Services\BusinessDayCalculator;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class IsoWorkflowController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'status' => ['nullable', 'in:draft,in_progress,completed,cancelled'],
            'type' => ['nullable', 'in:new,major,minor'],
            'overdue' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:newest,deadline,updated'],
            'per_page' => ['nullable', 'in:15,30,50'],
        ]);
        $overdueSteps = fn ($steps) => $steps->whereDate('deadline', '<', today())->where('status', '!=', 'completed');
        $query = IsoWorkflow::with(['program', 'steps.assignee'])->withCount([
            'steps',
            'steps as completed_steps' => fn ($steps) => $steps->where('status', 'completed'),
            'steps as overdue_steps' => $overdueSteps,
            'files',
        ]);
        $term = trim($filters['q'] ?? '');
        if ($term !== '') {
            $query->where(fn ($query) => $query->where('title', 'like', '%'.$term.'%')
                ->orWhereHas('program', fn ($program) => $program->where('name_vi', 'like', '%'.$term.'%')->orWhere('code', 'like', '%'.$term.'%')));
        }
        foreach (['program_id', 'status', 'type'] as $filter) {
            if (isset($filters[$filter])) {
                $query->where($filter, $filters[$filter]);
            }
        }
        if ($request->boolean('overdue')) {
            $query->whereHas('steps', $overdueSteps);
        }
        [$column, $direction] = [
            'newest' => ['created_at', 'desc'],
            'deadline' => ['expected_end_date', 'asc'],
            'updated' => ['updated_at', 'desc'],
        ][$filters['sort'] ?? 'newest'];

        return view('iso.index', [
            'workflows' => $query->orderBy($column, $direction)->orderBy('id')->paginate((int) ($filters['per_page'] ?? 15))->withQueryString(),
            'programs' => Program::orderBy('name_vi')->get(['id', 'name_vi']),
            'summary' => [
                'total' => IsoWorkflow::count(),
                'in_progress' => IsoWorkflow::where('status', 'in_progress')->count(),
                'completed' => IsoWorkflow::where('status', 'completed')->count(),
                'overdue' => IsoWorkflow::where('status', 'in_progress')->whereHas('steps', $overdueSteps)->count(),
            ],
        ]);
    }

    public function kanban(): View
    {
        $steps = IsoStep::with('workflow.program', 'assignee')->orderBy('deadline')->get()->groupBy('status');

        return view('iso.kanban', ['columns' => ['not_started' => 'Chưa bắt đầu', 'in_progress' => 'Đang thực hiện', 'waiting_library' => 'Đợi Thư viện', 'waiting_approval' => 'Đợi phê duyệt', 'completed' => 'Hoàn thành'], 'steps' => $steps]);
    }

    public function create(): View
    {
        return view('iso.create', ['programs' => Program::where('is_active', true)->orderBy('name_vi')->get()]);
    }

    public function store(Request $request, BusinessDayCalculator $calendar): RedirectResponse
    {
        $data = $request->validate(['program_id' => 'required|exists:programs,id', 'type' => 'required|in:new,major,minor', 'changed_credits' => 'required_unless:type,new|nullable|integer|min:0', 'title' => 'required|string|max:255', 'start_date' => 'required|date', 'note' => 'nullable|string']);
        $program = Program::findOrFail($data['program_id']);
        if ($data['type'] !== 'new') {
            $isMajor = $program->total_credits < $data['changed_credits'] * 5;
            if (($data['type'] === 'major') !== $isMajor) {
                throw ValidationException::withMessages(['changed_credits' => 'Chỉnh sửa lớn phải thay đổi trên 20% tổng tín chỉ; chỉnh sửa nhỏ không vượt mức này.']);
            }
        }
        $start = Carbon::parse($data['start_date']);
        $data['expected_end_date'] = $calendar->addMonths($start, 6);
        $data['status'] = 'in_progress';
        $workflow = IsoWorkflow::create($data);
        $steps = [['Khảo sát & đánh giá nhu cầu', 2], ['Biên soạn và cập nhật chương trình đào tạo', 4], ['Thẩm định & phê duyệt', 5], ['Thư viện chuẩn bị học liệu', 6]];
        foreach ($steps as $i => $step) {
            $workflow->steps()->create(['step_number' => $i + 1, 'step_name' => $step[0], 'deadline' => $calendar->addMonths($start, $step[1]), 'status' => $i === 0 ? 'in_progress' : 'not_started']);
        }

        return redirect()->route('iso.show', $workflow)->with('success', 'Đã tạo đợt rà soát.');
    }

    public function show(IsoWorkflow $iso): View
    {
        $iso->load(['program', 'steps.assignee', 'steps.histories.user', 'files.uploader']);

        return view('iso.show', ['workflow' => $iso, 'users' => User::permission('iso.assign')->get()]);
    }

    public function updateStep(Request $request, IsoWorkflow $iso, int $step): RedirectResponse
    {
        $item = $iso->steps()->findOrFail($step);
        $old = $item->only(['status', 'assigned_to_user_id', 'note']);
        $data = $request->validate(['status' => 'required|in:not_started,in_progress,waiting_library,waiting_approval,completed', 'note' => 'nullable|string']);
        $item->update($data);
        if ($item->wasChanged('status') && $item->status !== 'completed') {
            $item->update(['overdue_notified_at' => null]);
        }
        IsoHistory::create(['iso_step_id' => $item->id, 'user_id' => auth()->id(), 'action' => 'updated', 'old_values' => $old, 'new_values' => $item->only(['status', 'assigned_to_user_id', 'note'])]);
        if ($iso->status === 'completed' && $iso->closed_at === null) {
            $iso->update(['status' => 'in_progress']);
        }

        return back()->with('success', 'Đã cập nhật bước rà soát.');
    }

    public function upload(Request $request, IsoWorkflow $iso): RedirectResponse
    {
        $data = $request->validate(['file' => 'required|file|max:20480|mimes:pdf,doc,docx,jpg,jpeg,png']);
        $path = $data['file']->store('iso-files');
        IsoFile::create(['iso_workflow_id' => $iso->id, 'uploaded_by' => auth()->id(), 'original_name' => $data['file']->getClientOriginalName(), 'path' => $path, 'file_type' => $data['file']->getMimeType()]);

        return back()->with('success', 'Đã lưu hồ sơ rà soát.');
    }

    public function assign(Request $request, IsoWorkflow $iso, int $step): RedirectResponse
    {
        $item = $iso->steps()->findOrFail($step);
        $old = $item->only(['assigned_to_user_id']);
        $item->update($request->validate(['assigned_to_user_id' => ['nullable', 'exists:users,id']]));
        IsoHistory::create(['iso_step_id' => $item->id, 'user_id' => auth()->id(), 'action' => 'assigned', 'old_values' => $old, 'new_values' => $item->only(['assigned_to_user_id'])]);

        return back()->with('success', 'Đã giao người phụ trách.');
    }

    public function download(IsoWorkflow $iso, IsoFile $file): BinaryFileResponse
    {
        abort_unless($file->iso_workflow_id === $iso->id, 404);
        abort_unless(Storage::disk('local')->exists($file->path), 404);

        return response()->download(Storage::disk('local')->path($file->path), $file->original_name);
    }

    public function approve(IsoWorkflow $iso): RedirectResponse
    {
        $iso->update(['approved_by' => auth()->id(), 'approved_at' => now()]);

        return back()->with('success', 'Lãnh đạo đã phê duyệt đợt rà soát.');
    }

    public function close(IsoWorkflow $iso): RedirectResponse
    {
        abort_unless($iso->approved_at !== null, 422, 'Workflow phải được phê duyệt trước khi đóng.');
        $iso->update(['status' => 'completed', 'closed_by' => auth()->id(), 'closed_at' => now()]);

        return back()->with('success', 'Đã hoàn tất đợt rà soát.');
    }
}
