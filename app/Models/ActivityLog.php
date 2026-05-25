<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'user_id',
        'event_type',
        'subject_type',
        'subject_id',
        'subject_label',
        'description',
        'old_value',
        'new_value',
        'field_changed',
        'via_mcp',
        'debounce_key',
        'created_at',
    ];

    protected function casts(): array
    {
        return ['via_mcp' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
