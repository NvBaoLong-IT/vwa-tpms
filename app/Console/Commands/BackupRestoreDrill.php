<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Backup\Config\Config as BackupConfig;
use Spatie\Backup\Tasks\Backup\BackupJobFactory;
use Throwable;
use ZipArchive;

class BackupRestoreDrill extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:restore-drill {--connection=mysql} {--binary-path=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test a MySQL backup and restore using isolated disposable databases and synthetic files';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $connection = config('database.connections.'.$this->option('connection'));
        if (($connection['driver'] ?? null) !== 'mysql') {
            $this->error('Diễn tập này chỉ hỗ trợ MySQL.');

            return self::FAILURE;
        }
        $id = str_replace('-', '', (string) Str::uuid());
        $sourceName = 'vwa_backup_drill_'.$id;
        $restoreName = 'vwa_restore_drill_'.$id;
        $root = storage_path('framework/backup-drills/'.$id);
        $backupSettings = config('backup');
        $databaseSettings = config('database');
        $diskSettings = config('filesystems.disks.backups');
        $sourceCreated = false;
        $restoreCreated = false;

        try {
            $server = array_replace($connection, ['database' => null, 'url' => null]);
            config(['database.connections.backup_drill_server' => $server]);
            DB::connection('backup_drill_server')->statement('CREATE DATABASE `'.$sourceName.'` CHARACTER SET utf8mb4');
            $sourceCreated = true;
            DB::connection('backup_drill_server')->statement('CREATE DATABASE `'.$restoreName.'` CHARACTER SET utf8mb4');
            $restoreCreated = true;
            $dump = $connection['dump'] ?? [];
            if ($this->option('binary-path')) {
                $dump['dump_binary_path'] = $this->option('binary-path');
            }
            config([
                'database.connections.backup_drill_source' => array_replace($connection, ['database' => $sourceName, 'url' => null, 'dump' => $dump]),
                'database.connections.backup_drill_restore' => array_replace($connection, ['database' => $restoreName, 'url' => null]),
            ]);
            $source = DB::connection('backup_drill_source');
            $source->statement('CREATE TABLE restore_probe (id INT PRIMARY KEY, content VARCHAR(255))');
            $source->table('restore_probe')->insert(['id' => 1, 'content' => 'Dữ liệu diễn tập khôi phục']);
            $files = [
                'storage/app/private/diploma/minutes.txt' => 'Biên bản diễn tập',
                'storage/app/private/iso/evidence.txt' => 'Hồ sơ ISO diễn tập',
                'storage/app/public/probe.txt' => 'Tệp công khai diễn tập',
            ];
            foreach ($files as $path => $content) {
                File::ensureDirectoryExists(dirname($root.'/source/'.$path));
                File::put($root.'/source/'.$path, $content);
            }
            config([
                'backup.backup.name' => 'drill',
                'backup.backup.source.files.include' => [realpath($root.'/source/storage/app')],
                'backup.backup.source.files.exclude' => [],
                'backup.backup.source.files.relative_path' => realpath($root.'/source'),
                'backup.backup.source.databases' => ['backup_drill_source'],
                'backup.backup.temporary_directory' => $root.'/temporary',
                'backup.backup.password' => 'drill-'.$id,
                'backup.backup.destination.disks' => ['backups'],
                'filesystems.disks.backups.root' => $root.'/archives',
            ]);
            Storage::forgetDisk('backups');
            app()->forgetInstance(BackupConfig::class);
            BackupJobFactory::createFromConfig(BackupConfig::fromArray(config('backup')))->disableSignals()->run();
            $archives = File::glob($root.'/archives/drill/*.zip');
            if (count($archives) !== 1) {
                throw new \RuntimeException('Không tìm thấy đúng một bản sao lưu.');
            }
            $zip = new ZipArchive;
            if ($zip->open($archives[0]) !== true) {
                throw new \RuntimeException('Không mở được ZIP sao lưu.');
            }
            try {
                $zip->setPassword('drill-'.$id);
                $sql = null;
                $entries = [];
                for ($index = 0; $index < $zip->numFiles; $index++) {
                    $name = str_replace('\\', '/', $zip->getNameIndex($index));
                    $entries[$name] = $index;
                    if (str_starts_with($name, 'db-dumps/') && str_ends_with($name, '.sql')) {
                        $sql = $zip->getFromIndex($index);
                    }
                }
                if (! is_string($sql) || $sql === '') {
                    throw new \RuntimeException('Bản sao lưu thiếu dữ liệu SQL.');
                }
                DB::connection('backup_drill_restore')->unprepared($sql);
                $restored = DB::connection('backup_drill_restore')->table('restore_probe')->where('id', 1)->value('content');
                if ($restored !== 'Dữ liệu diễn tập khôi phục') {
                    throw new \RuntimeException('Dữ liệu khôi phục không khớp.');
                }
                foreach ($files as $path => $content) {
                    $bytes = isset($entries[$path]) ? $zip->getFromIndex($entries[$path]) : false;
                    if (! is_string($bytes) || hash('sha256', $bytes) !== hash('sha256', $content)) {
                        throw new \RuntimeException('Tệp khôi phục không khớp: '.$path);
                    }
                    File::ensureDirectoryExists(dirname($root.'/restored/'.$path));
                    File::put($root.'/restored/'.$path, $bytes);
                }
            } finally {
                $zip->close();
            }
            $this->info('PASS: MySQL dump → ZIP mã hóa → database mới; 3 tệp khôi phục khớp SHA-256.');
            $this->line('Diễn tập dùng dữ liệu tổng hợp; không kiểm tra dữ liệu nghiệp vụ hoặc máy chủ triển khai.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Diễn tập thất bại; xem storage/logs/laravel.log để chẩn đoán.');

            return self::FAILURE;
        } finally {
            foreach (['backup_drill_source', 'backup_drill_restore'] as $name) {
                DB::purge($name);
            }
            if ($sourceCreated) {
                DB::connection('backup_drill_server')->statement('DROP DATABASE `'.$sourceName.'`');
            }
            if ($restoreCreated) {
                DB::connection('backup_drill_server')->statement('DROP DATABASE `'.$restoreName.'`');
            }
            DB::purge('backup_drill_server');
            config(['backup' => $backupSettings, 'database' => $databaseSettings, 'filesystems.disks.backups' => $diskSettings]);
            Storage::forgetDisk('backups');
            app()->forgetInstance(BackupConfig::class);
        }
    }
}
