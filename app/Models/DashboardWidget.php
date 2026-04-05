<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DashboardWidget extends Model
{
    protected $fillable = [
        'project_id',
        'user_id',
        'widget_id',
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function widget()
    {
        return $this->belongsTo(Widget::class);
    }
}
