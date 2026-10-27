<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;
use LogicException;

class CurriculumWriteLock
{
    public static function acquire(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Khóa ghi CTĐT phải nằm trong giao dịch.');
        }
        abort_unless(DB::table('curriculum_write_locks')->where('id', 1)->lockForUpdate()->first() !== null, 503, 'Chưa khởi tạo khóa ghi CTĐT. Cần chạy migration.');
    }

    public static function transaction(Closure $operation): mixed
    {
        return DB::transaction(function () use ($operation): mixed {
            self::acquire();

            return $operation();
        }, 3);
    }
}
