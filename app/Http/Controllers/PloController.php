<?php

namespace App\Http\Controllers;

use App\Models\Plo;
use App\Models\Program;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PloController extends Controller
{
    public function index(Program $program): View
    {
        return view('programs.plos', ['program' => $program, 'plos' => $program->plos]);
    }

    public function store(Request $request, Program $program): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:30', 'unique:plos,code,NULL,id,program_id,'.$program->id], 'description_vi' => 'required|string', 'description_en' => 'nullable|string']);
        DB::transaction(function () use ($program, $data): void {
            $program = Program::whereKey($program->id)->lockForUpdate()->firstOrFail();
            $program->plos()->create($data);
        });

        return back()->with('success', 'Đã thêm chuẩn đầu ra.');
    }

    public function update(Request $request, Program $program, Plo $plo): RedirectResponse
    {
        abort_unless($plo->program_id === $program->id, 404);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:plos,code,'.$plo->id.',id,program_id,'.$program->id],
            'description_vi' => ['required', 'string'],
            'description_en' => ['nullable', 'string'],
        ]);
        DB::transaction(function () use ($program, $plo, $data): void {
            Program::whereKey($program->id)->lockForUpdate()->firstOrFail();
            $plo->update($data);
        });

        return back()->with('success', 'Đã cập nhật chuẩn đầu ra.');
    }

    public function destroy(Program $program, Plo $plo): RedirectResponse
    {
        abort_unless($plo->program_id === $program->id, 404);
        DB::transaction(function () use ($program, $plo): void {
            Program::whereKey($program->id)->lockForUpdate()->firstOrFail();
            $plo->delete();
        });

        return back()->with('success', 'Đã xóa chuẩn đầu ra.');
    }
}
