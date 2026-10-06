<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserAdminController extends Controller
{
    public function index(): View
    {
        return view('users.index', ['users' => User::with('roles')->paginate(20), 'roles' => Role::whereIn('name', ['Lãnh đạo phòng', 'Chuyên viên', 'Xem'])->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'email' => 'required|email|unique:users,email', 'role' => ['required', Rule::in(['Lãnh đạo phòng', 'Chuyên viên', 'Xem']), 'exists:roles,name']]);
        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make('password'), 'must_change_password' => true, 'email_verified_at' => now()]);
        $user->assignRole($data['role']);

        return back()->with('success', 'Đã tạo tài khoản. Mật khẩu tạm thời: password');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['role' => ['nullable', Rule::in(['Lãnh đạo phòng', 'Chuyên viên', 'Xem']), 'exists:roles,name'], 'is_active' => 'required|boolean']);
        $user->update(['is_active' => $data['is_active']]);
        if ($data['role']) {
            $user->syncRoles([$data['role']]);
        }

        return back()->with('success', 'Đã cập nhật tài khoản.');
    }
}
