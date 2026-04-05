<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WidgetResource;
use App\Models\Widget;

class WidgetController extends Controller
{
    public function index()
    {
        return WidgetResource::collection(
            Widget::where('is_active', true)->orderBy('category')->orderBy('name')->get()
        );
    }
}
