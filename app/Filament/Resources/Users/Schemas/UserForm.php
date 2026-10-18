<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->password()
                    // Wajib hanya saat CREATE — di edit jangan memaksa isi ulang
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state)), // jangan timpa hash saat kosong
                TextInput::make('phone')
                    ->tel(),
                // Gerbang akses panel /admin — hanya user tertentu
                Toggle::make('is_admin')
                    ->label('Admin')
                    ->default(false),
            ]);
    }
}
