<?php

namespace App\Http\Controllers;

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = LoginLog::with('user')->latest('logged_at');
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }
        if ($request->filled('successful')) {
            $query->where('successful', $request->boolean('successful'));
        }

        return view('users.login-logs', ['logs' => $query->paginate(30)->withQueryString(), 'users' => User::orderBy('name')->get()]);
    }
}
