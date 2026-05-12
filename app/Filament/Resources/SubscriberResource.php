<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriberResource\Pages;
use App\Models\Subscriber;
use App\Services\NewsletterService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriberResource extends Resource
{
    protected static ?string $model = Subscriber::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Users & Access';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('name')->maxLength(255),
            Forms\Components\TextInput::make('email')->email()->required(),
            Forms\Components\Select::make('status')->options(['active' => 'Active', 'unsubscribed' => 'Unsubscribed'])->default('active'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('email')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('name'),
            Tables\Columns\BadgeColumn::make('status')->colors(['success' => 'active', 'danger' => 'unsubscribed']),
            Tables\Columns\TextColumn::make('subscribed_at')->dateTime()->sortable(),
            Tables\Columns\TextColumn::make('unsubscribed_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->options(['active' => 'Active', 'unsubscribed' => 'Unsubscribed']),
        ])->actions([
            Tables\Actions\Action::make('unsubscribe')
                ->icon('heroicon-o-no-symbol')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (Subscriber $record): bool => $record->status === 'active')
                ->action(function (Subscriber $record): void {
                    app(NewsletterService::class)->unsubscribe($record->token);

                    Notification::make()
                        ->title("Subscriber unsubscribed: {$record->email}")
                        ->success()
                        ->send();
                }),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
            ->defaultSort('subscribed_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscribers::route('/'),
            'create' => Pages\CreateSubscriber::route('/create'),
            'edit' => Pages\EditSubscriber::route('/{record}/edit'),
        ];
    }
}
