<?php
namespace App\Filament\Resources;
use App\Filament\Resources\MediaResource\Pages;
use App\Models\Media;
use Filament\Forms;
use Filament\Schemas\Components\Section as SchemaSection;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MediaResource extends Resource
{
    protected static ?string $model = Media::class;
    protected static \UnitEnum|string|null $navigationGroup = 'Media';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    public static function form(Schema $schema): Schema {
        return $schema->components([
            Forms\Components\TextInput::make('alt_text')->maxLength(255),
            Forms\Components\Select::make('collection')->options(['images'=>'Images','audio'=>'Audio','video'=>'Video'])->default('images'),
            Forms\Components\Toggle::make('is_starred')->label('Starred'),
        ]);
    }

    public static function table(Table $table): Table {
        return $table->columns([
            Tables\Columns\ImageColumn::make('path')->disk('public')->label('Preview')->square(),
            Tables\Columns\TextColumn::make('filename')->searchable()->limit(40),
            Tables\Columns\TextColumn::make('mime_type')->label('Type'),
            Tables\Columns\TextColumn::make('size')->formatStateUsing(fn($s) => number_format($s/1024,1).' KB'),
            Tables\Columns\IconColumn::make('is_starred')->boolean()->label('★'),
            Tables\Columns\TextColumn::make('user.name')->label('Uploaded by'),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('collection')->options(['images'=>'Images','audio'=>'Audio','video'=>'Video']),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
          ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
          ->defaultSort('created_at','desc');
    }

    public static function getRelations(): array { return []; }
    public static function getPages(): array {
        return [
            'index' => Pages\ListMedia::route('/'),
            'edit' => Pages\EditMedia::route('/{record}/edit'),
        ];
    }
}
