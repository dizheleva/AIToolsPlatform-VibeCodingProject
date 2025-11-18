<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TwoFactorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'two_factor_enabled' => $this->two_factor_enabled ?? false,
            'two_factor_type' => $this->two_factor_type ?? 'none',
            'has_telegram_chat_id' => !empty($this->telegram_chat_id),
            'two_factor_verified_at' => $this->two_factor_verified_at?->toISOString(),
        ];
    }
}

