<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThreadEntry extends Model
{
    use HasFactory;

    /** Allowed entry types (Phase 1). `decision` gains a lifecycle in Phase 3. */
    public const TYPES = ['momentum', 'decision', 'dead_end', 'gotcha'];

    /** Allowed capture triggers. `session_end` is reserved for a future distinct tag. */
    public const TRIGGERS = ['manual', 'commit', 'session_end'];

    protected $fillable = [
        'project_id',
        'task_id',
        'created_by',
        'type',
        'content',
        'trigger',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
