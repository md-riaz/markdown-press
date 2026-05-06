<?php
namespace App\Filament\Resources;
use App\Filament\Resources\SubscriberResource\Pages;
use App\Models\Subscriber;
use Filament\Forms;
use Filament\Schemas\Components\Section as SchemaSection;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriberResource extends Resource
{
    protected static ?string $model = Subscriber::class;
    protected static \UnitEnum|string|null $navigationGroup = 'Users & Access';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    public static function form(Schema $schema): Schema {
        return $schema->components([
            Forms\Components\TextInput::make('name')->maxLength(255),
            Forms\Components\TextInput::make('email')->email()->required(),
            Forms\Components\Select::make('status')->options(['active'=>'Active','unsubscribed'=>'Unsubscribed'])->default('active'),
        ]);
    }

    public static function table(Table $table): Table {
        return $table->columns([
            Tables\Columns\TextColumn::make('email')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('name'),
            Tables\Columns\BadgeColumn::make('status')->colors(['success'=>'active','danger'=>'unsubscribed']),
            Tables\Columns\TextColumn::make('subscribed_at')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->options(['active'=>'Active','unsubscribed'=>'Unsubscribed']),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
          ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
          ->defaultSort('subscribed_at','desc');
    }

    public static function getRelations(): array { return []; }
    public static function getPages(): array {
        return [
            'index' => Pages\ListSubscribers::route('/'),
            'create' => Pages\CreateSubscriber::route('/create'),
            'edit' => Pages\EditSubscriber::route('/{record}/edit'),
        ];
    }
}
