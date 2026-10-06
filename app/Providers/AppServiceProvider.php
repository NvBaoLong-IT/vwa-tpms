<?php

namespace App\Providers;

use App\Models\Course;
use App\Models\Plo;
use App\Models\Program;
use App\Observers\CurriculumAuditObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Program::observe(CurriculumAuditObserver::class);
        Course::observe(CurriculumAuditObserver::class);
        Plo::observe(CurriculumAuditObserver::class);
    }
}
