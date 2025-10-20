<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Recommendation\RecommendationEngine;

class ProductController extends Controller
{
    public function show(int $productId, RecommendationEngine $engine)
    {
        $detail = $engine->productDetail($productId);

        if ($detail === null) {
            abort(404);
        }

        return response()->json($detail);
    }
}
