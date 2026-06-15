<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrafficResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $dataLimitBytes = $this->integerValue('data_limit');
        $usedTrafficBytes = $this->integerValue('used_traffic');
        $remainingTrafficBytes = $dataLimitBytes === null
            ? null
            : max(0, $dataLimitBytes - $usedTrafficBytes);

        return [
            'username' => $this->resource['username'] ?? null,
            'status' => $this->resource['status'] ?? null,
            'data_limit_bytes' => $dataLimitBytes,
            'used_traffic_bytes' => $usedTrafficBytes,
            'remaining_traffic_bytes' => $remainingTrafficBytes,
            'usage_percent' => $this->usagePercent($dataLimitBytes, $usedTrafficBytes),
            'lifetime_used_traffic_bytes' => $this->integerValue('lifetime_used_traffic'),
            'subscription_url' => $this->resource['subscription_url'] ?? null,
            'updated_at' => now()->toISOString(),
        ];
    }

    private function integerValue(string $key): ?int
    {
        $value = $this->resource[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    private function usagePercent(?int $dataLimitBytes, ?int $usedTrafficBytes): ?float
    {
        if (! $dataLimitBytes || $dataLimitBytes <= 0 || $usedTrafficBytes === null) {
            return null;
        }

        return round(min(100, ($usedTrafficBytes / $dataLimitBytes) * 100), 2);
    }
}
