<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform produk → JSON untuk API.
     * Resource = kontrak response yang versionable — ubah bentuk JSON
     * tanpa menyentuh controller, field sensitif gak bocor karena
     * hanya field yang ditulis di sini yang ikut.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'price' => $this->price,
            'stock' => $this->stock,
            'options' => $this->options,
            // withCount → attribute, bukan relasi (lihat catatan di OrderResource)
            'order_items_count' => $this->when(isset($this->order_items_count), fn () => $this->order_items_count),
        ];
    }
}
