<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class McpHistory extends Model
{
    protected $table = 'mcp_history';

    public const UPDATED_AT = null; // immutable log — created_at only

    protected $fillable = [
        'mcp_token_id',
        'user_id',
        'project_id',
        'tool',
        'arguments',
        'status',
        'duration_ms',
        'result_summary',
        'error_message',
    ];

    protected $casts = [
        'arguments' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function token()
    {
        return $this->belongsTo(McpToken::class, 'mcp_token_id');
    }
}
