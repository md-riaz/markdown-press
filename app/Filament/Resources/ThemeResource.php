<?php
namespace App\Filament\Resources;

use App\Filament\Resources\ThemeResource\Pages;
use App\Models\Theme;
use App\Modules\Theme\ThemeRegistry;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section as SchemaSection;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ThemeResource extends Resource
{
    protected static ?string $model = Theme::class;
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paint-brush';

    public static function form(Schema $schema): Schema {
        return $schema->components([
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('slug')->required()->unique(Theme::class,'slug',ignoreRecord:true),
            Forms\Components\Textarea::make('description')->rows(2),
            Forms\Components\Toggle::make('is_active')->label('Active'),
            Forms\Components\Toggle::make('is_default')->label('Default'),
        ]);
    }

    public static function table(Table $table): Table {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('slug'),
            Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
            Tables\Columns\IconColumn::make('is_default')->boolean()->label('Default'),
        ])->actions([
            Tables\Actions\Action::make('activate')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (Theme $record): bool => ! $record->is_active)
                ->action(function (Theme $record): void {
                    app(ThemeRegistry::class)->activate($record);

                    Notification::make()
                        ->title("Activated theme: {$record->name}")
                        ->success()
                        ->send();
                }),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ])
          ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array { return []; }
    public static function getPages(): array {
        return [
            'index' => Pages\ListThemes::route('/'),
            'create' => Pages\CreateTheme::route('/create'),
            'edit' => Pages\EditTheme::route('/{record}/edit'),
        ];
    }
}
