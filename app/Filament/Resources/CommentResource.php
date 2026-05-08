<?php
namespace App\Filament\Resources;
use App\Filament\Resources\CommentResource\Pages;
use App\Models\Comment;
use Filament\Forms;
use Filament\Schemas\Components\Section as SchemaSection;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CommentResource extends Resource
{
    protected static ?string $model = Comment::class;
    protected static \UnitEnum|string|null $navigationGroup = 'Content';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';

    public static function form(Schema $schema): Schema {
        return $schema->components([
            Forms\Components\Select::make('status')->options(['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'])->required(),
            Forms\Components\Textarea::make('body')->rows(4)->disabled(),
        ]);
    }

    public static function table(Table $table): Table {
        return $table->columns([
            Tables\Columns\TextColumn::make('post.title')->label('Post')->limit(40)->searchable(),
            Tables\Columns\TextColumn::make('display_name')->label('Author')->limit(25),
            Tables\Columns\TextColumn::make('body')->limit(60),
            Tables\Columns\BadgeColumn::make('status')->colors(['warning'=>'pending','success'=>'approved','danger'=>'rejected']),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->options(['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected']),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
          ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
          ->defaultSort('created_at','desc');
    }

    public static function getRelations(): array { return []; }
    public static function getPages(): array {
        return [
            'index' => Pages\ListComments::route('/'),
            'edit' => Pages\EditComment::route('/{record}/edit'),
        ];
    }
}
