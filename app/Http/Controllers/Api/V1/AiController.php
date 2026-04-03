<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;

class AiController extends Controller
{
    public function index(Project $project)
    {
        // Phase 4: return AI conversation history for this project
        return response()->json(['data' => []]);
    }

    public function store(Project $project)
    {
        // Phase 4: create a new AI conversation
        return response()->json(['message' => 'AI features coming in Phase 4.'], 501);
    }
}
