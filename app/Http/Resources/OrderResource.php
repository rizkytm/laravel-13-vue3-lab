<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value, // enum cast → string untuk JSON
            'total' => $this->total,
            'transaction_id' => $this->transaction_id, // null sampai job ProcessPayment sukses
            'paid_at' => $this->paid_at?->toIso8601String(),
            // withCount/loadCount mengisi ATTRIBUTE (bukan relasi) —
            // relationLoaded('items_count') selalu false. isset pada attribute
            // = key muncul hanya kalau count memang diminta di query.
            'items_count' => $this->when(isset($this->items_count), fn () => $this->items_count),
            // whenLoaded: key hanya muncul kalau relasi sudah di-eager-load —
            // kalau lupa with('user'), JSON tidak berisi user: null yang menyesatkan
            'user' => $this->when($this->relationLoaded('user'), fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
