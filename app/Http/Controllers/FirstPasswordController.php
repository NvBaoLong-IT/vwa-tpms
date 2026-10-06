<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class FirstPasswordController extends Controller
{
    public function edit(): View
    {
        return view('auth.first-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'confirmed', 'string', 'min:8']]);
        $request->user()->update(['password' => Hash::make($data['password']), 'must_change_password' => false]);

        return redirect()->route('dashboard')->with('success', 'Đã đổi mật khẩu thành công.');
    }
}
