<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class RecordUserActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $route = $request->route()?->getName();
        if ($user === null || $route === null || $request->routeIs('schedule.tick')
            || ($request->isMethod('GET') && ! preg_match('/export|reload|\.word$|\.document$|\.download$|\.minutes$/', $route))) {
            return $next($request);
        }
        try {
            $response = $next($request);
            $status = $response->getStatusCode();
            $successful = $status < 400 && ! in_array('errors', $request->session()->get('_flash.new', []), true);
        } catch (Throwable $exception) {
            $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : ($exception instanceof ValidationException ? 422 : 500);
            $this->record($request, $user, $route, $status, false);
            throw $exception;
        }
        $this->record($request, $user, $route, $status, $successful);

        return $response;
    }

    private function record(Request $request, Model $user, string $route, int $status, bool $successful): void
    {
        $module = match (explode('.', $route)[0]) {
            'programs', 'courses' => 'curriculum',
            'academic' => 'academic',
            'iso' => 'iso',
            'diplomas' => 'diploma',
            'reports' => 'reports',
            'users' => 'users',
            default => 'account',
        };
        $action = match (true) {
            $route === 'logout' => 'logout',
            str_contains($route, '.notifications.') => 'read',
            str_contains($route, 'password.') => 'password',
            str_ends_with($route, '.attach') => 'attach',
            str_ends_with($route, '.detach') => 'detach',
            str_ends_with($route, '.assign') => 'assign',
            str_ends_with($route, '.files.upload'), str_contains($route, '.materials.store') => 'upload',
            str_contains($route, 'export'), str_contains($route, 'reload'), str_ends_with($route, '.word') => 'export',
            str_contains($route, 'download'), str_ends_with($route, '.document'), str_ends_with($route, '.minutes') => 'download',
            str_contains($route, 'import') => 'import',
            str_ends_with($route, '.restore') => 'restore',
            str_ends_with($route, '.approve') => 'approve',
            str_ends_with($route, '.reject') => 'reject',
            str_ends_with($route, '.close') => 'close',
            str_ends_with($route, '.cancel') => 'cancel',
            str_ends_with($route, '.publish') => 'publish',
            str_ends_with($route, '.scan') => 'scan',
            str_ends_with($route, '.upload') => 'upload',
            $request->isMethod('DELETE') => 'delete',
            $request->isMethod('POST') => 'create',
            default => 'update',
        };
        $subjects = [];
        foreach ($request->route()->parameters() as $key => $value) {
            if ($value instanceof Model) {
                $subjects[$key] = $value->getKey();
            } elseif (is_scalar($value) && ctype_digit((string) $value)) {
                $subjects[$key] = (int) $value;
            }
        }
        ActivityLog::create([
            'user_id' => User::whereKey($user->id)->exists() ? $user->id : null, 'actor_name' => $user->name, 'module' => $module, 'action' => $action,
            'route_name' => $route, 'subjects' => $subjects, 'successful' => $successful,
            'status_code' => $status, 'ip_address' => $request->ip(),
        ]);
    }
}
