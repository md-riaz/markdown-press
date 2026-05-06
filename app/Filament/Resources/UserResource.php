<?php
namespace App\Filament\Resources;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Schemas\Components\Section as SchemaSection;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static \UnitEnum|string|null $navigationGroup = 'Users & Access';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    public static function form(Schema $schema): Schema {
        return $schema->components([
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('email')->email()->required()->unique(User::class,'email',ignoreRecord:true),
            Forms\Components\TextInput::make('username')->maxLength(100)->unique(User::class,'username',ignoreRecord:true),
            Forms\Components\Select::make('role')->options(['admin'=>'Admin','editor'=>'Editor','author'=>'Author'])->required()->default('author'),
            Forms\Components\Textarea::make('bio')->rows(3),
            Forms\Components\TextInput::make('avatar_url')->url()->maxLength(512),
            Forms\Components\TextInput::make('password')->password()->minLength(8)
                ->dehydrateStateUsing(fn($s) => $s ? bcrypt($s) : null)
                ->dehydrated(fn($s) => filled($s))
                ->required(fn(string $context) => $context === 'create'),
        ]);
    }

    public static function table(Table $table): Table {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('email')->searchable(),
            Tables\Columns\BadgeColumn::make('role')->colors(['danger'=>'admin','primary'=>'editor','success'=>'author']),
            Tables\Columns\TextColumn::make('posts_count')->counts('posts')->label('Posts'),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('role')->options(['admin'=>'Admin','editor'=>'Editor','author'=>'Author']),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
          ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array { return []; }
    public static function getPages(): array {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
