<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskCompletion extends Model
{
    use HasFactory;

    public const UPDATED_AT = null; // rows are immutable; no updated_at column

    protected $fillable = [
        'task_id',
        'completed_by_user_id',
        'summary_what',
        'summary_why',
        'source',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }
}
