<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveWidgetLayoutRequest;
use App\Http\Requests\StoreDashboardWidgetRequest;
use App\Http\Requests\UpdateDashboardWidgetRequest;
use App\Http\Resources\DashboardWidgetResource;
use App\Models\DashboardWidget;
use App\Models\Project;

class DashboardController extends Controller
{
    public function index(Project $project)
    {
        return DashboardWidgetResource::collection($project->dashboardWidgets);
    }

    public function store(StoreDashboardWidgetRequest $request, Project $project)
    {
        $widget = $project->dashboardWidgets()->create($request->validated());

        return new DashboardWidgetResource($widget);
    }

    public function update(UpdateDashboardWidgetRequest $request, Project $project, DashboardWidget $widget)
    {
        abort_if($widget->project_id !== $project->id, 404);

        $widget->update($request->validated());

        return new DashboardWidgetResource($widget);
    }

    public function destroy(Project $project, DashboardWidget $widget)
    {
        abort_if($widget->project_id !== $project->id, 404);

        $widget->delete();

        return response()->json(['message' => 'Widget removed.']);
    }

    public function saveLayout(SaveWidgetLayoutRequest $request, Project $project)
    {
        foreach ($request->layout as $item) {
            $project->dashboardWidgets()
                ->where('id', $item['id'])
                ->update([
                    'grid_x' => $item['grid_x'],
                    'grid_y' => $item['grid_y'],
                    'grid_w' => $item['grid_w'],
                    'grid_h' => $item['grid_h'],
                ]);
        }

        return response()->json(['message' => 'Layout saved.']);
    }
}
