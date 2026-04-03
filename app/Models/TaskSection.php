<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskSection extends Model
{
    protected $fillable = [
        'project_id',
        'name',
        'position',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'section_id');
    }
}
