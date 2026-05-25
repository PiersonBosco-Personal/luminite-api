<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\Project;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request, Project $project)
    {
        $query = ActivityLog::where('project_id', $project->id)
            ->with('user')
            ->orderByDesc('created_at');

        // Category filter
        $category = $request->query('category');
        if ($category === 'tasks') {
            $query->where('event_type', 'like', 'task.%');
        } elseif ($category === 'mcp') {
            $query->where('via_mcp', true);
        } elseif ($category === 'labels_sections') {
            $query->where(function ($q) {
                $q->where('event_type', 'like', 'section.%')
                  ->orWhere('event_type', 'like', 'label.%');
            });
        } elseif ($category === 'tech_stack') {
            $query->where('event_type', 'like', 'tech_stack.%');
        }

        // Keyword search
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('subject_label', 'like', "%{$search}%");
            });
        }

        // User scope filter
        if ($userIds = $request->query('user_ids')) {
            $query->whereIn('user_id', (array) $userIds);
        }

        $logs = $query->paginate(15);

        return ActivityLogResource::collection($logs);
    }
}
