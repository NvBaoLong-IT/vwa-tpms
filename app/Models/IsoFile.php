<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IsoFile extends Model
{
    protected $fillable = ['iso_workflow_id', 'uploaded_by', 'original_name', 'path', 'file_type'];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
