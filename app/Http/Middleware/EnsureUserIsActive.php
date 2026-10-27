<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $current = $user?->fresh();
        if ($user !== null && (! ($current?->is_active ?? false)
            || (int) $request->session()->get('auth_session_version', 0) !== (int) $current->session_version)) {
            Auth::guard('web')->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = $current?->is_active
                ? 'Phiên đăng nhập đã bị thu hồi. Vui lòng đăng nhập lại.'
                : 'Tài khoản đã bị khóa. Vui lòng liên hệ người quản lý.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 401)
                : redirect()->route('login')->withErrors(['email' => $message]);
        }

        return $next($request);
    }
}
