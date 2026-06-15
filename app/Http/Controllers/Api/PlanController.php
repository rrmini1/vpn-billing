<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlanController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $plans = Plan::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return PlanResource::collection($plans);
    }
}
