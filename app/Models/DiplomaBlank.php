<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiplomaBlank extends Model
{
    use SoftDeletes;

    protected $fillable = ['serial_number', 'type', 'import_date', 'status', 'note'];

    protected function casts(): array
    {
        return ['import_date' => 'date'];
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(DiplomaLedger::class, 'serial_number', 'serial_number')->withTrashed();
    }
}
