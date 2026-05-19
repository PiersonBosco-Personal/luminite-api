<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttachmentFolder extends Model
{
    protected $fillable = [
        'project_id',
        'created_by',
        'parent_id',
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

    public function parent()
    {
        return $this->belongsTo(AttachmentFolder::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(AttachmentFolder::class, 'parent_id')->orderBy('position');
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'folder_id');
    }
}
