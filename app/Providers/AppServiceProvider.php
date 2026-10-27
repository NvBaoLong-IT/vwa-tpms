<?php

namespace App\Providers;

use App\Models\Course;
use App\Models\Plo;
use App\Models\Program;
use App\Observers\CurriculumAuditObserver;
use App\Services\CurriculumAuditService;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurriculumAuditService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            if ($event->guard === 'web' && $event->user->is_active && request()->hasSession()) {
                request()->session()->put('auth_session_version', (int) $event->user->session_version);
            }
        });
        Program::observe(CurriculumAuditObserver::class);
        Course::observe(CurriculumAuditObserver::class);
        Plo::observe(CurriculumAuditObserver::class);
    }
}
