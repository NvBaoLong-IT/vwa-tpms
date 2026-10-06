<?php

use App\Http\Controllers\AcademicNotificationController;
use App\Http\Controllers\AcademicWarningController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseMaterialController;
use App\Http\Controllers\CurriculumAuditController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiplomaController;
use App\Http\Controllers\FirstPasswordController;
use App\Http\Controllers\IsoWorkflowController;
use App\Http\Controllers\LoginLogController;
use App\Http\Controllers\PloController;
use App\Http\Controllers\PloMatrixController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\ProgramImportController;
use App\Http\Controllers\ProgramVersionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserAdminController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'permission:dashboard.view'])->name('dashboard');
Route::middleware('auth')->group(function () {
    Route::get('first-password', [FirstPasswordController::class, 'edit'])->name('password.first.edit');
    Route::put('first-password', [FirstPasswordController::class, 'update'])->name('password.first.update');
});
Route::middleware(['auth', 'permission:reports.view|dashboard.view'])->group(function () {
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{type}/{format}', [ReportController::class, 'export'])->middleware('permission:reports.export|dashboard.view')->name('reports.export');
    Route::get('reports/history/{history}/reload', [ReportController::class, 'reload'])->middleware('permission:reports.export|dashboard.view')->name('reports.reload');
});
Route::middleware(['auth', 'permission:users.manage'])->group(function () {
    Route::get('users', [UserAdminController::class, 'index'])->name('users.index');
    Route::post('users', [UserAdminController::class, 'store'])->name('users.store');
    Route::put('users/{user}', [UserAdminController::class, 'update'])->name('users.update');
    Route::get('users/login-logs', [LoginLogController::class, 'index'])->name('users.login-logs');
});
Route::middleware(['auth', 'permission:programs.view'])->get('/modules/curriculum', function () {
    return response()->json(['module' => 'curriculum', 'status' => 'authorized']);
})->name('modules.curriculum');

Route::middleware(['auth', 'permission:programs.view'])->group(function () {
    Route::get('programs', [ProgramController::class, 'index'])->middleware('permission:programs.view')->name('programs.index');
    Route::get('programs/export.csv', [ProgramController::class, 'exportCsv'])->name('programs.export.csv');
    Route::get('programs/audit', [CurriculumAuditController::class, 'index'])->middleware('permission:programs.view')->name('programs.audit');
    Route::get('programs/create', [ProgramController::class, 'create'])->middleware('permission:programs.create')->name('programs.create');
    Route::get('programs/import', [ProgramImportController::class, 'create'])->middleware('permission:programs.import')->name('programs.import.form');
    Route::get('programs/import/template', [ProgramImportController::class, 'template'])->middleware('permission:programs.import')->name('programs.import.template');
    Route::post('programs/import', [ProgramImportController::class, 'store'])->middleware('permission:programs.import')->name('programs.import');
    Route::post('programs', [ProgramController::class, 'store'])->middleware('permission:programs.create')->name('programs.store');
    Route::get('programs/{program}/edit', [ProgramController::class, 'edit'])->middleware('permission:programs.update')->name('programs.edit');
    Route::get('programs/{program}', [ProgramController::class, 'show'])->middleware('permission:programs.view')->name('programs.show');
    Route::post('programs/{program}/versions', [ProgramVersionController::class, 'store'])->middleware('permission:programs.update')->name('programs.versions.store');
    Route::get('programs/{program}/versions/{version}', [ProgramVersionController::class, 'show'])->middleware('permission:programs.view')->name('programs.versions.show');
    Route::get('programs/{program}/versions/{version}/export.docx', [ProgramVersionController::class, 'exportWord'])->middleware('permission:programs.view')->name('programs.versions.word');
    Route::post('programs/{program}/versions/{version}/publish', [ProgramVersionController::class, 'publish'])->middleware('role:Lãnh đạo phòng')->name('programs.versions.publish');
    Route::put('programs/{program}', [ProgramController::class, 'update'])->middleware('permission:programs.update')->name('programs.update');
    Route::delete('programs/{program}', [ProgramController::class, 'destroy'])->middleware('permission:programs.delete')->name('programs.destroy');
    Route::post('programs/{program}/courses', [ProgramController::class, 'attachCourse'])->middleware('permission:courses.assign')->name('programs.courses.attach');
    Route::put('programs/{program}/courses/{course}', [ProgramController::class, 'updateCourse'])->middleware('permission:courses.assign')->name('programs.courses.update');
    Route::delete('programs/{program}/courses/{course}', [ProgramController::class, 'detachCourse'])->middleware('permission:courses.assign')->name('programs.courses.detach');
    Route::get('programs/{program}/matrix', [PloMatrixController::class, 'index'])->name('programs.matrix');
    Route::put('programs/{program}/matrix', [PloMatrixController::class, 'update'])->middleware('permission:programs.update')->name('programs.matrix.update');
    Route::get('courses', [CourseController::class, 'index'])->middleware('permission:courses.view')->name('courses.index');
    Route::get('courses/create', [CourseController::class, 'create'])->middleware('permission:courses.create')->name('courses.create');
    Route::post('courses', [CourseController::class, 'store'])->middleware('permission:courses.create')->name('courses.store');
    Route::get('courses/{course}/edit', [CourseController::class, 'edit'])->middleware('permission:courses.update')->name('courses.edit');
    Route::get('courses/{course}', [CourseController::class, 'show'])->middleware('permission:courses.view')->name('courses.show');
    Route::put('courses/{course}', [CourseController::class, 'update'])->middleware('permission:courses.update')->name('courses.update');
    Route::delete('courses/{course}', [CourseController::class, 'destroy'])->middleware('permission:courses.delete')->name('courses.destroy');
    Route::post('courses/{course}/materials', [CourseMaterialController::class, 'store'])->middleware('permission:courses.update')->name('courses.materials.store');
    Route::put('courses/{course}/materials/{material}', [CourseMaterialController::class, 'update'])->middleware('permission:courses.update')->name('courses.materials.update');
    Route::delete('courses/{course}/materials/{material}', [CourseMaterialController::class, 'destroy'])->middleware('permission:courses.update')->name('courses.materials.destroy');
    Route::get('iso', [IsoWorkflowController::class, 'index'])->middleware('permission:iso.view')->name('iso.index');
    Route::get('iso/kanban', [IsoWorkflowController::class, 'kanban'])->middleware('permission:iso.view')->name('iso.kanban');
    Route::get('iso/create', [IsoWorkflowController::class, 'create'])->middleware('permission:iso.create')->name('iso.create');
    Route::post('iso', [IsoWorkflowController::class, 'store'])->middleware('permission:iso.create')->name('iso.store');
    Route::get('iso/{iso}', [IsoWorkflowController::class, 'show'])->middleware('permission:iso.view')->name('iso.show');
    Route::get('programs/{program}/plos', [PloController::class, 'index'])->middleware('permission:programs.view')->name('programs.plos');
    Route::post('programs/{program}/plos', [PloController::class, 'store'])->middleware('permission:programs.update')->name('programs.plos.store');
    Route::put('programs/{program}/plos/{plo}', [PloController::class, 'update'])->middleware('permission:programs.update')->name('programs.plos.update');
    Route::delete('programs/{program}/plos/{plo}', [PloController::class, 'destroy'])->middleware('permission:programs.update')->name('programs.plos.destroy');
    Route::patch('iso/{iso}/steps/{step}', [IsoWorkflowController::class, 'updateStep'])->middleware('permission:iso.update')->name('iso.steps.update');
    Route::patch('iso/{iso}/steps/{step}/assignment', [IsoWorkflowController::class, 'assign'])->middleware('permission:iso.assign')->name('iso.steps.assign');
    Route::post('iso/{iso}/files', [IsoWorkflowController::class, 'upload'])->middleware('permission:iso.upload')->name('iso.files.upload');
    Route::get('iso/{iso}/files/{file}', [IsoWorkflowController::class, 'download'])->middleware('permission:iso.view')->name('iso.files.download');
    Route::post('iso/{iso}/approve', [IsoWorkflowController::class, 'approve'])->middleware('permission:iso.approve')->name('iso.approve');
    Route::post('iso/{iso}/close', [IsoWorkflowController::class, 'close'])->middleware('permission:iso.close')->name('iso.close');
});

Route::middleware(['auth', 'permission:academic.view'])->get('/modules/academic', function () {
    return response()->json(['module' => 'academic', 'status' => 'authorized']);
})->name('modules.academic');

Route::middleware(['auth', 'permission:students.view|academic.view'])->group(function () {
    Route::get('academic/students', [AcademicWarningController::class, 'students'])->name('academic.students');
    Route::get('academic/students/{student}', [AcademicWarningController::class, 'student'])->name('academic.student');
});

Route::middleware(['auth', 'permission:academic.view'])->group(function () {
    Route::get('academic/notifications', [AcademicNotificationController::class, 'index'])->name('academic.notifications');
    Route::post('academic/notifications/read-all', [AcademicNotificationController::class, 'readAll'])->name('academic.notifications.read-all');
    Route::post('academic/notifications/{notification}/read', [AcademicNotificationController::class, 'read'])->name('academic.notifications.read');
    Route::get('academic', [AcademicWarningController::class, 'index'])->name('academic.index');
    Route::get('academic/students/{student}/edit', [AcademicWarningController::class, 'editStudent'])->middleware('permission:academic.process')->name('academic.student.edit');
    Route::put('academic/students/{student}/assignment', [AcademicWarningController::class, 'updateAssignment'])->middleware('permission:academic.process')->name('academic.assignment.update');
    Route::put('academic/students/{student}/progress', [AcademicWarningController::class, 'updateProgress'])->middleware('permission:academic.process')->name('academic.progress.update');
    Route::put('academic/students/{student}/milestones', [AcademicWarningController::class, 'updateMilestones'])->middleware('permission:academic.process')->name('academic.milestones.update');
    Route::get('academic/import', [AcademicWarningController::class, 'importForm'])->middleware('permission:academic.import')->name('academic.import.form');
    Route::post('academic/import', [AcademicWarningController::class, 'import'])->middleware('permission:academic.import')->name('academic.import');
    Route::get('academic/imports/{import}', [AcademicWarningController::class, 'importReport'])->name('academic.import.report');
    Route::get('academic/imports/{import}/errors.csv', [AcademicWarningController::class, 'downloadImportErrors'])->name('academic.import.errors');
    Route::post('academic/scan', [AcademicWarningController::class, 'scan'])->middleware('permission:academic.process')->name('academic.scan');
    Route::get('academic/settings', [AcademicWarningController::class, 'settings'])->middleware('role:Lãnh đạo phòng')->name('academic.settings');
    Route::put('academic/settings', [AcademicWarningController::class, 'updateSettings'])->middleware('role:Lãnh đạo phòng')->name('academic.settings.update');
    Route::patch('academic/warnings/{warning}', [AcademicWarningController::class, 'update'])->middleware('permission:academic.process')->name('academic.warnings.update');
    Route::get('academic/export.xlsx', [AcademicWarningController::class, 'exportExcel'])->name('academic.export.excel');
    Route::get('academic/export.pdf', [AcademicWarningController::class, 'exportPdf'])->name('academic.export.pdf');
    Route::get('academic/export.doc', [AcademicWarningController::class, 'exportWord'])->name('academic.export.word');
});

Route::middleware(['auth', 'permission:diploma.view'])->get('/modules/diploma', function () {
    return response()->json(['module' => 'diploma', 'status' => 'authorized']);
})->name('modules.diploma');
Route::middleware(['auth', 'permission:diploma.view'])->group(function () {
    Route::get('diplomas', [DiplomaController::class, 'index'])->name('diplomas.index');
    Route::post('diplomas', [DiplomaController::class, 'store'])->middleware('permission:diploma.issue')->name('diplomas.store');
    Route::post('diploma-requests/{diplomaRequest}/approve', [DiplomaController::class, 'approve'])->middleware('permission:diploma.approve')->name('diplomas.requests.approve');
    Route::post('diploma-requests/{diplomaRequest}/reject', [DiplomaController::class, 'reject'])->middleware('permission:diploma.approve')->name('diplomas.requests.reject');
    Route::post('diplomas/blanks', [DiplomaController::class, 'storeBlank'])->middleware('permission:diploma.update')->name('diplomas.blanks.store');
    Route::patch('diplomas/blanks/{blank}', [DiplomaController::class, 'updateBlank'])->middleware('permission:diploma.update')->name('diplomas.blanks.update');
    Route::put('diplomas/{ledger}', [DiplomaController::class, 'update'])->middleware('permission:diploma.update')->name('diplomas.update');
    Route::post('diplomas/{ledger}/cancel', [DiplomaController::class, 'cancel'])->middleware('permission:diploma.approve')->name('diplomas.cancel');
    Route::get('diplomas/{ledger}/{type}', [DiplomaController::class, 'document'])->middleware('permission:diploma.view')->name('diplomas.document');
    Route::get('diplomas-audit', [DiplomaController::class, 'audit'])->middleware('permission:diploma.view')->name('diplomas.audit');
    Route::get('diplomas-audit/blanks/{audit}/minutes', [DiplomaController::class, 'downloadBlankMinutes'])->middleware('permission:diploma.view')->name('diplomas.blanks.minutes');
    Route::get('diplomas-import', [DiplomaController::class, 'importForm'])->middleware('permission:diploma.issue')->name('diplomas.import.form');
    Route::post('diplomas-import', [DiplomaController::class, 'import'])->middleware('permission:diploma.issue')->name('diplomas.import');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
