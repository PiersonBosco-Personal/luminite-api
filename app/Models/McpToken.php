<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class McpToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_id',
        'name',
        'token',
        'scopes',
        'last_used_at',
        'request_count',
        'expires_at',
    ];

    protected $casts = [
        'scopes'       => 'array',
        'last_used_at' => 'datetime',
        'expires_at'   => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? []);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public static function generate(User $user, Project $project, string $name, array $scopes): array
    {
        $raw   = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $raw);

        $token = static::create([
            'user_id'    => $user->id,
            'project_id' => $project->id,
            'name'       => $name,
            'token'      => $hash,
            'scopes'     => $scopes,
        ]);

        return [$token, $raw];
    }
}
