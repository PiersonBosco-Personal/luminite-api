<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TechStack extends Model
{
    protected $fillable = [
        'project_id',
        'name',
        'app_label',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
