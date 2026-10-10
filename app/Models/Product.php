<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'price', 'stock', 'options', 'is_active', 'image_path',
    ];

    /**
     * Cast via method casts() (gaya L12+) — alternatif property $casts.
     * 'options' => array: JSON dari DB otomatis jadi array PHP, dan sebaliknya.
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'stock' => 'integer',
            'options' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Model event: created/dispatched SEBELUM insert.
     * ??= → kalau slug sudah diisi (factory), event tidak menimpa.
     */
    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            $product->slug ??= Str::slug($product->name).'-'.Str::lower(Str::random(4));
        });

        // Cache invalidation: produk berubah (save/delete) → lupakan cache list.
        // Tanpa ini, halaman produk menyajikan data basi selama TTL-nya habis.
        static::saved(fn (Product $product) => Cache::forget('products.index'));
        static::deleted(fn (Product $product) => Cache::forget('products.index'));
    }

    /**
     * Accessor syntax L11+ (method bertipe Attribute): nilai TURUNAN
     * yang dihitung saat dibaca — tidak jadi kolom di DB.
     * $product->formatted_price
     */
    protected function formattedPrice(): Attribute
    {
        return Attribute::get(fn (): string => 'Rp '.number_format($this->price, 0, ',', '.'));
    }

    /**
     * Scope: query yang bisa dipakai ulang → Product::inStock()
     * Kompilasi jadi WHERE, bukan filter PHP setelah load semua baris.
     */
    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock', '>', 0)->where('is_active', true);
    }

    /**
     * Filter JSON column: where('options->color', 'red')
     * Jalan di SQLite (JSON1) dan MySQL — tanpa kolom tambahan.
     */
    public function scopeWithOption(Builder $query, string $key, mixed $value): Builder
    {
        return $query->where("options->{$key}", $value);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Kurangi stok aman dari race condition.
     * lockForUpdate: baris DIKUNCI sampai transaction selesai —
     * dua request paralel tidak bisa baca stok yang sama lalu oversell.
     * Baca → cek → tulis harus dalam SATU transaction.
     */
    public function buy(int $quantity): bool
    {
        return DB::transaction(function () use ($quantity): bool {
            $stock = static::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->value('stock');

            if ($stock < $quantity) {
                return false;
            }

            static::query()
                ->whereKey($this->getKey())
                ->update(['stock' => $stock - $quantity]);

            $this->refresh();

            return true;
        });
        // CATATAN: event StockUpdated TIDAK di-fire di sini — buy() bisa
        // dipanggil DALAM transaction yang lebih besar (checkout M4).
        // Fire event setelah commit = tanggung jawab caller (lihat
        // ProductController@buy & Api CheckoutController).
    }
}
