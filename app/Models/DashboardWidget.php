<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DashboardWidget extends Model
{
    protected $fillable = [
        'project_id',
        'type',
        'config',
        'grid_x',
        'grid_y',
        'grid_w',
        'grid_h',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
