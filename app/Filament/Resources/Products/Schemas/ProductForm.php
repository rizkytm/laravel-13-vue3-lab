<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('price')
                    ->label('Harga')
                    ->required()
                    ->numeric()
                    ->prefix('Rp'),
                // Stok READ-ONLY: hanya boleh berubah lewat InventoryService
                // (movement in/out/adjust) — tidak ada edit manual tanpa jejak.
                Placeholder::make('stock')
                    ->label('Stok (via movement)')
                    ->content(fn ($record) => (string) ($record?->stock ?? 0)),
                Textarea::make('options')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->required(),
                FileUpload::make('image_path')
                    ->image(),
            ]);
    }
}
