<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'task_id',
        'user_id',
        'work_type_id',
        'description',
        'duration_minutes',
        'started_at',
        'stopped_at',
        'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'stopped_at' => 'datetime',
            'logged_at'  => 'date',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function workType()
    {
        return $this->belongsTo(WorkType::class);
    }

    public function isRunning(): bool
    {
        return is_null($this->duration_minutes) && ! is_null($this->started_at);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('duration_minutes')->whereNotNull('started_at');
    }
}
