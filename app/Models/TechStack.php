<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TechStack extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'parent_id',
        'name',
        'version',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function parent()
    {
        return $this->belongsTo(TechStack::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(TechStack::class, 'parent_id');
    }
}
