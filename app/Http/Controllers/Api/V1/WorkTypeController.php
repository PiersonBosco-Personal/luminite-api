<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkTypeRequest;
use App\Http\Requests\UpdateWorkTypeRequest;
use App\Http\Resources\WorkTypeResource;
use App\Models\Project;
use App\Models\WorkType;

class WorkTypeController extends Controller
{
    public function index(Project $project)
    {
        $workTypes = $project->workTypes()->orderBy('name')->get();

        return WorkTypeResource::collection($workTypes);
    }
}
