<?php

namespace App\Models;

use App\Observers\DecisionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([DecisionObserver::class])]
class Decision extends Model
{
    use HasFactory;

    /** Lifecycle states. A decision is the current truth until something supersedes it. */
    public const STATUSES = ['active', 'superseded'];

    protected $fillable = [
        'project_id',
        'task_id',
        'created_by',
        'decision',
        'rationale',
        'status',
        'superseded_by_id',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(Decision::class, 'superseded_by_id');
    }
}
