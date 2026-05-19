<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    protected $fillable = [
        'attachable_id',
        'attachable_type',
        'uploaded_by',
        'folder_id',
        'filename',
        'original_name',
        'url',
        'mime_type',
        'path',
        'disk',
        'size',
    ];

    public function attachable()
    {
        return $this->morphTo();
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function folder()
    {
        return $this->belongsTo(AttachmentFolder::class, 'folder_id');
    }

    public function getProject(): Project
    {
        if ($this->attachable_type === 'App\\Models\\Project') {
            return $this->attachable;
        }
        return $this->attachable->project;
    }
}
