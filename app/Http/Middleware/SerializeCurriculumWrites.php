<?php

namespace App\Http\Middleware;

use App\Services\CurriculumEditGuard;
use App\Services\CurriculumWriteLock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SerializeCurriculumWrites
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || $request->routeIs('programs.document-imports.store', 'programs.document-imports.new.store')) {
            return $next($request);
        }

        return CurriculumWriteLock::transaction(function () use ($request, $next): Response {
            app(CurriculumEditGuard::class)->assertRequest($request);

            return $next($request);
        });
    }
}
