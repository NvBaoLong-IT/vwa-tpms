<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
        DB::transaction(function () use ($user, $data): void {
            $target = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $role = $data['role'] ?? null;
            $roleChanged = $role !== null && $target->getRoleNames()->sort()->values()->all() !== [$role];
            $revoke = ! (bool) $data['is_active'] || $roleChanged;
            $target->is_active = $data['is_active'];
            if ($revoke) {
                $target->session_version++;
                $target->setRememberToken(Str::random(60));
            }
            $target->save();
            if ($role !== null) {
                $target->syncRoles([$role]);
            }
            if ($revoke && config('session.driver') === 'database') {
                DB::connection(config('session.connection'))
                    ->table(config('session.table', 'sessions'))->where('user_id', $target->id)->delete();
            }
        });

        return back()->with('success', 'Đã cập nhật tài khoản.');
    }
}
