<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\DiplomaBlank;
use App\Models\DiplomaLedger;
use App\Models\Program;
use App\Models\Student;
use App\Services\CurriculumWriteLock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeletedRecordController extends Controller
{
    public const TYPES = [
        'programs' => [Program::class, 'Chương trình đào tạo', 'name_vi'],
        'courses' => [Course::class, 'Học phần', 'name_vi'],
        'students' => [Student::class, 'Người học', 'full_name'],
        'diploma_ledgers' => [DiplomaLedger::class, 'Sổ văn bằng', 'reg_number'],
        'diploma_blanks' => [DiplomaBlank::class, 'Phôi văn bằng', 'serial_number'],
    ];

    public function index(Request $request): View
    {
        $type = $request->validate(['type' => ['nullable', 'in:'.implode(',', array_keys(self::TYPES))]])['type'] ?? 'programs';
        [$model, $label, $nameField] = self::TYPES[$type];

        return view('records.deleted', [
            'types' => self::TYPES, 'type' => $type, 'label' => $label, 'nameField' => $nameField,
            'records' => $model::onlyTrashed()->orderByDesc('deleted_at')->paginate(20)->withQueryString(),
        ]);
    }

    public function restore(string $type, int $record): RedirectResponse
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        [$model] = self::TYPES[$type];
        CurriculumWriteLock::transaction(function () use ($model, $record): void {
            $model::onlyTrashed()->whereKey($record)->lockForUpdate()->firstOrFail()->restore();
        });

        return back()->with('success', 'Đã khôi phục bản ghi cùng các liên kết được lưu giữ.');
    }
}
