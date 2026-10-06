<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseMaterial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CourseMaterialController extends Controller
{
    public function store(Request $request, Course $course): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'material_type' => ['required', 'in:textbook,reference,other'], 'author' => ['nullable', 'string', 'max:255'], 'publisher' => ['nullable', 'string', 'max:255'], 'isbn' => ['nullable', 'string', 'max:50']]);
        $course->materials()->create($data);

        return back()->with('success', 'Đã thêm học liệu.');
    }

    public function update(Request $request, Course $course, CourseMaterial $material): RedirectResponse
    {
        abort_unless($material->course_id === $course->id, 404);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'material_type' => ['required', 'in:textbook,reference,other'], 'author' => ['nullable', 'string', 'max:255'], 'publisher' => ['nullable', 'string', 'max:255'], 'isbn' => ['nullable', 'string', 'max:50']]);
        $material->update($data);

        return back()->with('success', 'Đã cập nhật học liệu.');
    }

    public function destroy(Course $course, CourseMaterial $material): RedirectResponse
    {
        abort_unless($material->course_id === $course->id, 404);
        if ($material->confirmations()->exists()) {
            throw ValidationException::withMessages(['material' => 'Học liệu đã có lịch sử xác nhận, không thể xóa.']);
        }
        $material->delete();

        return back()->with('success', 'Đã xóa học liệu.');
    }
}
