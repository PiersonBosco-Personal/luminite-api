<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NoteFolder extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'created_by',
        'name',
        'position',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function notes()
    {
        return $this->hasMany(Note::class, 'folder_id');
    }
}
