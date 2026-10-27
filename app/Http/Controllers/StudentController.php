<?php

namespace App\Http\Controllers;

use App\Jobs\ImportStudentsJob;
use App\Models\AcademicImport;
use App\Models\Program;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function create(): View
    {
        return view('academic.student-create', ['programs' => Program::where('is_active', true)->orderBy('name_vi')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $student = Student::create($this->validated($request));

        return redirect()->route('academic.student', $student)->with('success', 'Đã thêm hồ sơ sinh viên.');
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $student->update($this->validated($request, $student));

        return back()->with('success', 'Đã cập nhật hồ sơ sinh viên.');
    }

    public function importForm(): View
    {
        return view('academic.student-import');
    }

    public function import(Request $request): RedirectResponse
    {
        $data = $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:51200']]);
        $path = $data['file']->store('student-imports');
        $batch = AcademicImport::create(['user_id' => $request->user()->id, 'original_name' => $data['file']->getClientOriginalName(), 'kind' => 'student_master', 'status' => 'queued']);
        ImportStudentsJob::dispatch($path, $request->user()->id, $batch->id);

        return redirect()->route('academic.students')->with('success', config('queue.default') === 'sync' ? 'Đã nhập hồ sơ sinh viên.' : 'Tệp hồ sơ sinh viên đang chờ xử lý.');
    }

    public function template(): Response
    {
        return response("MaSV,HoTen,NgaySinh,MaNganh,Lop,Khoa,NamNhapHoc,TrinhDo,Email,TrangThai\nSV001,Nguyen Van A,01/09/2004,CTDT01,K45A,Khoa A,2022,dai_hoc,a@example.edu.vn,active\n", 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="mau-ho-so-sinh-vien.csv"']);
    }

    private function validated(Request $request, ?Student $student = null): array
    {
        return $request->validate([
            'student_code' => ['required', 'string', 'max:50', 'unique:students,student_code'.($student ? ','.$student->id : '')],
            'full_name' => ['required', 'string', 'max:255'], 'date_of_birth' => ['nullable', 'date'],
            'class_code' => ['nullable', 'string', 'max:50'], 'program_id' => ['nullable', 'exists:programs,id,deleted_at,NULL'],
            'faculty_name' => ['nullable', 'string', 'max:150'], 'enrollment_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'degree_level' => ['required', 'in:dai_hoc,thac_si,tien_si'], 'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', 'in:active,graduated,withdrawn'],
        ]);
    }
}
