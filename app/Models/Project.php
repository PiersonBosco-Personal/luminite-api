<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'owner_id',
        'name',
        'description',
        'status',
        'goals',
        'architecture_notes',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function invitations()
    {
        return $this->hasMany(ProjectInvitation::class);
    }

    public function taskSections()
    {
        return $this->hasMany(TaskSection::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function notes()
    {
        return $this->hasMany(Note::class);
    }

    public function noteFolders()
    {
        return $this->hasMany(NoteFolder::class);
    }

    public function attachmentFolders()
    {
        return $this->hasMany(AttachmentFolder::class);
    }

    public function labels()
    {
        return $this->hasMany(Label::class);
    }

    public function techStacks()
    {
        return $this->hasMany(TechStack::class);
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function dashboardWidgets()
    {
        return $this->hasMany(DashboardWidget::class);
    }

    public function workTypes()
    {
        return $this->hasMany(WorkType::class);
    }

    public function timeEntries()
    {
        return $this->hasMany(TimeEntry::class);
    }
}
