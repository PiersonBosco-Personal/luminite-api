<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Widget extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'category',
        'description',
        'icon',
        'default_config',
        'is_premium',
        'is_active',
        'sort_order',
        'default_w',
        'default_h',
        'min_w',
        'min_h',
    ];

    protected function casts(): array
    {
        return [
            'default_config' => 'array',
            'is_premium'     => 'boolean',
            'is_active'      => 'boolean',
        ];
    }

    public function dashboardWidgets()
    {
        return $this->hasMany(DashboardWidget::class);
    }
}
