<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkType extends Model
{
    use HasFactory;

    /**
     * Full set of allowed color slugs, including the neutral fallback.
     * The frontend in luminite-web-app maps these slugs to Tailwind classes;
     * the backend just stores and validates the string.
     */
    public const PALETTE = [
        'red', 'orange', 'amber', 'yellow', 'lime', 'green',
        'teal', 'cyan', 'blue', 'indigo', 'purple', 'pink',
        'slate',
    ];

    /**
     * Subset auto-assigned to newly created user work types. Slate is
     * excluded because it is the neutral fallback for "no color set".
     */
    public const ASSIGNABLE_COLORS = [
        'red', 'orange', 'amber', 'yellow', 'lime', 'green',
        'teal', 'cyan', 'blue', 'indigo', 'purple', 'pink',
    ];

    protected $fillable = [
        'project_id',
        'name',
        'is_active',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function timeEntries()
    {
        return $this->hasMany(TimeEntry::class);
    }
}
