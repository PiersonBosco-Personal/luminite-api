<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveWidgetLayoutRequest;
use App\Http\Requests\StoreDashboardWidgetRequest;
use App\Http\Resources\DashboardWidgetResource;
use App\Models\DashboardWidget;
use App\Models\Project;
use App\Models\Widget;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * GET /v1/projects/{project}/dashboard-widgets
     * Returns the authenticated user's placed widgets for this project.
     */
    public function index(Project $project)
    {
        $widgets = $project->dashboardWidgets()
            ->where('user_id', auth()->id())
            ->with('widget')
            ->get();

        return DashboardWidgetResource::collection($widgets);
    }

    /**
     * POST /v1/projects/{project}/dashboard-widgets
     * Add a widget to the authenticated user's dashboard for this project.
     */
    public function store(StoreDashboardWidgetRequest $request, Project $project)
    {
        $catalogWidget = Widget::findOrFail($request->widget_id);

        // Place below the lowest existing widget for this user/project
        $maxY = $project->dashboardWidgets()
            ->where('user_id', auth()->id())
            ->selectRaw('MAX(grid_y + grid_h) as max_y')
            ->value('max_y') ?? 0;

        $dashboardWidget = $project->dashboardWidgets()->create([
            'user_id'   => auth()->id(),
            'widget_id' => $catalogWidget->id,
            'grid_x'    => 0,
            'grid_y'    => $maxY,
            'grid_w'    => $catalogWidget->default_w,
            'grid_h'    => $catalogWidget->default_h,
        ]);

        return new DashboardWidgetResource($dashboardWidget->load('widget'));
    }

    /**
     * POST /v1/projects/{project}/dashboard-widgets/sync
     * Persist a full layout update (sent by react-grid-layout on drag/resize).
     * Layout items use react-grid-layout keys: { i, x, y, w, h }.
     */
    public function sync(SaveWidgetLayoutRequest $request, Project $project)
    {
        DB::transaction(function () use ($request, $project) {
            foreach ($request->layout as $item) {
                $project->dashboardWidgets()
                    ->where('id', $item['i'])
                    ->where('user_id', auth()->id())
                    ->update([
                        'grid_x' => $item['x'],
                        'grid_y' => $item['y'],
                        'grid_w' => $item['w'],
                        'grid_h' => $item['h'],
                    ]);
            }
        });

        return response()->json(['message' => 'Layout saved.']);
    }

    /**
     * DELETE /v1/dashboard-widgets/{dashboardWidget}
     * Remove a widget from the dashboard. Not project-scoped so the frontend
     * can call it without knowing the project ID.
     */
    public function destroy(DashboardWidget $dashboardWidget)
    {
        abort_if($dashboardWidget->user_id !== auth()->id(), 403);

        $dashboardWidget->delete();

        return response()->json(['message' => 'Widget removed.']);
    }
}
