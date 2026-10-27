<?php

namespace App\Http\Controllers;

use App\Models\AcademicImport;
use App\Models\DocumentImport;
use App\Models\WebScheduledRun;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class OperationsStatusController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): View
    {
        $heartbeat = WebScheduledRun::where('run_key', 'scheduler-heartbeat')->first();
        $lastBackup = null;
        $backupReadable = true;
        try {
            $disk = Storage::disk('backups');
            foreach ($disk->files(config('backup.backup.name')) as $path) {
                if (str_ends_with($path, '.zip')) {
                    $lastBackup = max($lastBackup ?? 0, $disk->lastModified($path));
                }
            }
        } catch (Throwable $exception) {
            report($exception);
            $backupReadable = false;
        }

        return view('operations.status', [
            'heartbeat' => $heartbeat,
            'schedulerHealthy' => $heartbeat?->status === 'completed' && $heartbeat->finished_at?->gte(now()->subMinutes(35)),
            'runs' => WebScheduledRun::whereNotIn('task', ['system', 'heartbeat'])->latest('started_at')->limit(20)->get(),
            'lastBackup' => $lastBackup ? Carbon::createFromTimestamp($lastBackup) : null,
            'backupReadable' => $backupReadable,
            'backupHealthy' => $lastBackup !== null && $lastBackup >= now()->subHours(26)->timestamp,
            'queueIsSync' => config('queue.default') === 'sync',
            'pendingDocuments' => DocumentImport::where('status', 'processing')->count(),
            'pendingImports' => AcademicImport::whereIn('status', ['queued', 'processing'])->count(),
            'failedImports' => AcademicImport::where('status', 'failed')->count(),
        ]);
    }
}
