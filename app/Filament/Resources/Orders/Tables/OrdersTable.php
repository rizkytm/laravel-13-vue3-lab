<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\RefundService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => match ($state) {
                        OrderStatus::Pending => 'Pending',
                        OrderStatus::Paid => 'Paid',
                        OrderStatus::Cancelled => 'Cancelled',
                        OrderStatus::Failed => 'Failed',
                        OrderStatus::Refunded => 'Refunded',
                    })
                    ->color(fn (OrderStatus $state): string => match ($state) {
                        OrderStatus::Pending => 'warning',
                        OrderStatus::Paid => 'success',
                        OrderStatus::Cancelled, OrderStatus::Failed => 'danger',
                        OrderStatus::Refunded => 'gray',
                    }),
                TextColumn::make('total')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('transaction_id')
                    ->searchable(),
                TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                // Aksi status MENGGUNAKAN domain (bukan edit kolom mentah):
                // cancel() & refund() melewati status machine + restock berjejak.
                Action::make('cancel')
                    ->label('Batalkan')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record): bool => $record->status->canTransitionTo(OrderStatus::Cancelled))
                    ->action(function (Order $record) {
                        $record->cancel();

                        Notification::make()
                            ->success()
                            ->title('Order dibatalkan & stok dikembalikan')
                            ->send();
                    }),
                Action::make('refund')
                    ->label('Refund')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record): bool => $record->status->canTransitionTo(OrderStatus::Refunded))
                    ->action(function (Order $record) {
                        try {
                            app(RefundService::class)->refund($record);

                            Notification::make()
                                ->success()
                                ->title('Refund berhasil — uang kembali & stok dipulihkan')
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->danger()
                                ->title('Refund gagal di gateway')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
