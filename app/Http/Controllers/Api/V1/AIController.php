<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\AIDriverContract;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AIController extends Controller
{
    public function __construct(private AIDriverContract $ai) {}

    public function summary(Request $request): JsonResponse
    {
        $request->validate(['markdown' => 'required|string']);
        return response()->json(['summary' => $this->ai->summarize($request->markdown)]);
    }

    public function excerpt(Request $request): JsonResponse
    {
        $request->validate(['markdown' => 'required|string', 'words' => 'integer|min:10|max:200']);
        return response()->json(['excerpt' => $this->ai->excerpt($request->markdown, $request->words ?? 50)]);
    }

    public function translate(Request $request): JsonResponse
    {
        $request->validate(['markdown' => 'required|string', 'locale' => 'required|string|max:10']);
        return response()->json(['translated' => $this->ai->translate($request->markdown, $request->locale)]);
    }
}
