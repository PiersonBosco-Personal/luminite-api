<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Label extends Model
{
    protected $fillable = [
        'project_id',
        'name',
        'color',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks()
    {
        return $this->morphedByMany(Task::class, 'labelable');
    }

    public function notes()
    {
        return $this->morphedByMany(Note::class, 'labelable');
    }
}
