<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StaticBuild;
use App\Models\Theme;
use App\Modules\StaticGen\BuildOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuildController extends Controller
{
    public function __construct(private BuildOrchestrator $orchestrator) {}

    public function index(): JsonResponse
    {
        return response()->json(StaticBuild::with('theme')->latest()->paginate(20));
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(StaticBuild::with('theme')->findOrFail($id));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate(['theme_slug' => 'nullable|string']);

        $theme = $request->theme_slug
            ? Theme::where('slug', $request->theme_slug)->firstOrFail()
            : Theme::where('is_active', true)->firstOrFail();

        $build = $this->orchestrator->build($theme);
        return response()->json($build, 201);
    }
}
