<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Recommendation\RecommendationEngine;

class HomepageRecommendationsController extends Controller
{
    public function index(RecommendationEngine $engine)
    {
        return response()->json([
            'data' => $engine->homepageRecommendations(),
        ]);
    }
}
