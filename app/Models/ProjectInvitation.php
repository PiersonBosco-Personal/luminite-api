<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ProjectInvitation extends Model
{
    protected $fillable = [
        'project_id',
        'invited_by',
        'email',
        'expires_at',
        'accepted_at',
        'declined_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at'  => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query
            ->whereNull('accepted_at')
            ->whereNull('declined_at')
            ->where('expires_at', '>', Carbon::now());
    }

    public function getStatusAttribute(): string
    {
        if ($this->accepted_at) {
            return 'accepted';
        }
        if ($this->declined_at) {
            return 'declined';
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return 'expired';
        }
        return 'pending';
    }
}
