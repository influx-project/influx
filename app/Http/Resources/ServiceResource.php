<?php

namespace App\Http\Resources;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Service
 */
class ServiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'location' => $this->location,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'host' => $this->host,
            'port' => $this->port,
            'use_ssl' => $this->use_ssl,
            'importance' => $this->importance->value,
            'importance_label' => $this->importance->label(),
            'check_interval' => $this->check_interval,
            'timeout' => $this->timeout,
            'collect_metrics' => $this->collect_metrics,
            'stream_metrics' => $this->stream_metrics,
            'enabled' => $this->enabled,
            'user_id' => $this->user_id,
            'owner' => $this->whenLoaded('owner', fn () => $this->owner === null ? null : [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'email' => $this->owner->email,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
