<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $plans = Plan::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return PlanResource::collection($plans);
    }

    public function store(Request $request): JsonResponse
    {
        $plan = Plan::query()->create($this->validatePlan($request));

        return (new PlanResource($plan))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Plan $plan): PlanResource
    {
        $plan->update($this->validatePlan($request, $plan));

        return new PlanResource($plan->refresh());
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePlan(Request $request, ?Plan $plan = null): array
    {
        return $request->validate([
            'code' => [
                $plan ? 'sometimes' : 'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('plans', 'code')->ignore($plan?->id),
            ],
            'name' => [$plan ? 'sometimes' : 'required', 'string', 'max:255'],
            'traffic_limit_bytes' => [$plan ? 'sometimes' : 'required', 'integer', 'min:1'],
            'price_amount' => [$plan ? 'sometimes' : 'required', 'integer', 'min:0'],
            'currency' => [$plan ? 'sometimes' : 'required', 'string', 'size:3'],
            'is_active' => [$plan ? 'sometimes' : 'required', 'boolean'],
            'sort_order' => [$plan ? 'sometimes' : 'required', 'integer', 'min:0'],
        ]);
    }
}
