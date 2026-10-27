<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = ['user_id', 'actor_name', 'module', 'action', 'route_name', 'subjects', 'successful', 'status_code', 'ip_address'];

    protected function casts(): array
    {
        return ['subjects' => 'array', 'successful' => 'boolean'];
    }
}
