<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                // Status READ-ONLY di form — perubahan hanya lewat aksi
                // tabel (cancel/refund) yang melewati status machine.
                Placeholder::make('status')
                    ->content(fn ($record) => $record?->status?->value ?? 'pending'),
                TextInput::make('total')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('transaction_id'),
                DateTimePicker::make('paid_at'),
            ]);
    }
}
