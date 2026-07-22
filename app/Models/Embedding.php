<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Embedding extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'source_type',
        'source_id',
        'content_hash',
        'embedding',    // written only under pgsql via a raw vector literal (see EmbedRecord)
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
