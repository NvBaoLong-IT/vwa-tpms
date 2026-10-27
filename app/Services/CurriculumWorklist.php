<?php

namespace App\Services;

use App\Models\CreditRecognition;
use App\Models\DocumentImport;
use App\Models\ProgramExcelImport;
use App\Models\ProgramVersion;
use Illuminate\Http\Request;

class CurriculumWorklist
{
    public function forUser(Request $request): array
    {
        $user = $request->user();
        $manager = $user->hasRole('Lãnh đạo phòng');
        $groups = [];
        if ($user->can('programs.view') && $user->can('programs.update')) {
            $query = ProgramVersion::with('program')->whereIn('status', $manager ? ['submitted', 'approved'] : ['draft', 'submitted', 'returned', 'approved'])
                ->whereHas('program', function ($query) use ($request): void {
                    if ($request->filled('program_id')) {
                        $query->whereKey($request->integer('program_id'));
                    }
                    if ($request->filled('degree_level')) {
                        $query->where('degree_level', $request->input('degree_level'));
                    }
                })
                ->when($request->filled('year'), fn ($query) => $query->where('cohort_year', $request->integer('year')))
                ->when(! $manager, fn ($query) => $query->where('created_by', $user->id));
            $groups[] = [
                'title' => 'Phiên bản CTĐT cần xử lý', 'scope' => $manager ? 'Theo bộ lọc ngành, trình độ và khóa; chờ duyệt hoặc công bố.' : 'Phiên bản do bạn tạo, theo bộ lọc ngành, trình độ và khóa.',
                'count' => (clone $query)->count(),
                'items' => $query->orderBy('created_at')->orderBy('id')->limit(5)->get()->map(fn ($version) => ['label' => $version->snapshot['program']['name_vi'].' · K'.$version->cohort_year.' lần '.$version->revision, 'detail' => $version->statusLabel().' · '.$version->decision_number, 'url' => route('programs.versions.show', [$version->program_id, $version])])->all(),
            ];
        }
        if ($user->can('programs.view') && $user->can('programs.import')) {
            $query = ProgramExcelImport::whereIn('status', $manager ? ['submitted', 'approved'] : ['review', 'submitted', 'returned', 'approved'])->where('expires_at', '>', now())
                ->when(! $manager, fn ($query) => $query->where('created_by', $user->id));
            $groups[] = [
                'title' => 'Bản nhập Excel CTĐT cần xử lý', 'scope' => $manager ? 'Toàn hệ thống, còn hạn; không áp dụng các bộ lọc trên Dashboard.' : 'Các bản nhập của bạn còn hạn; không áp dụng bộ lọc trên Dashboard.',
                'count' => (clone $query)->count(),
                'items' => $query->orderBy('created_at')->orderBy('id')->limit(5)->get()->map(fn ($import) => ['label' => $import->program_code.' · '.$import->filename, 'detail' => $import->statusLabel().' · Hạn '.$import->expires_at->format('d/m/Y H:i'), 'url' => route('programs.import.preview', $import)])->all(),
            ];
        }
        if ($user->can('students.manage')) {
            $query = CreditRecognition::with(['student', 'version'])->whereIn('status', $manager ? ['submitted'] : ['submitted', 'returned'])
                ->whereHas('student', function ($query) use ($request): void {
                    foreach (['program_id' => 'program_id', 'degree_level' => 'degree_level', 'year' => 'enrollment_year'] as $filter => $column) {
                        if ($request->filled($filter)) {
                            $query->where($column, $request->input($filter));
                        }
                    }
                })->when(! $manager, fn ($query) => $query->where('submitted_by', $user->id));
            $groups[] = [
                'title' => 'Công nhận tín chỉ cần xử lý', 'scope' => $manager ? 'Hồ sơ chờ duyệt theo bộ lọc sinh viên; người duyệt phải khác người trình.' : 'Hồ sơ bạn đã trình hoặc được trả về, theo bộ lọc sinh viên.',
                'count' => (clone $query)->count(),
                'items' => $query->orderBy('created_at')->orderBy('id')->limit(5)->get()->map(fn ($item) => ['label' => '#'.$item->id.' · '.$item->student->student_code.' · '.$item->student->full_name, 'detail' => ($item->status === 'returned' ? 'Trả về bổ sung' : 'Chờ phê duyệt').' · '.$item->decision_number, 'url' => route('academic.credits', [$item->student, 'version_id' => $item->program_version_id])])->all(),
            ];
        }

        if ($user->can('programs.view') && $user->can('programs.import')) {
            $query = DocumentImport::with('program')->whereIn('status', $manager ? ['submitted', 'approved'] : ['review', 'submitted', 'returned', 'approved'])
                ->where(fn ($query) => $query->whereNull('approval_expires_at')->orWhere('approval_expires_at', '>', now()))
                ->when(! $manager, fn ($query) => $query->where('uploaded_by', $user->id));
            $groups[] = [
                'title' => 'Bản nhập AI CTĐT cần xử lý', 'scope' => $manager ? 'Toàn hệ thống, còn hạn; không áp dụng bộ lọc trên Dashboard.' : 'Các tài liệu AI của bạn còn hạn; không áp dụng bộ lọc trên Dashboard.',
                'count' => (clone $query)->count(),
                'items' => $query->orderBy('created_at')->orderBy('id')->limit(5)->get()->map(fn ($draft) => ['label' => $draft->original_name, 'detail' => $draft->statusLabel(), 'url' => $draft->program ? route('programs.document-imports.show', [$draft->program, $draft]) : route('programs.document-imports.new.show', $draft)])->all(),
            ];
        }

        return $groups;
    }
}
