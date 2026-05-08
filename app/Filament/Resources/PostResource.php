<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PostResource\Pages;
use App\Models\Post;
use App\Models\Category;
use App\Models\Tag;
use Filament\Forms;
use Filament\Schemas\Components\Section as SchemaSection;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;
    protected static \UnitEnum|string|null $navigationGroup = 'Content';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Content')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(512)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, callable $set) =>
                            $set('slug', Str::slug($state))
                        ),
                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(512)
                        ->unique(Post::class, 'slug', ignoreRecord: true),
                    Forms\Components\Textarea::make('content_markdown')
                        ->label('Content (Markdown)')
                        ->required()
                        ->rows(20)
                        ->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make('Publish')
                ->schema([
                    Forms\Components\Select::make('status')
                        ->options(['draft' => 'Draft', 'published' => 'Published', 'scheduled' => 'Scheduled'])
                        ->default('draft')
                        ->required(),
                    Forms\Components\DateTimePicker::make('published_at'),
                    Forms\Components\DateTimePicker::make('scheduled_at'),
                    Forms\Components\Toggle::make('is_featured')->label('Featured'),
                ])->columns(2),

            Forms\Components\Section::make('Taxonomy')
                ->schema([
                    Forms\Components\Select::make('categories')
                        ->relationship('categories', 'name')
                        ->multiple()
                        ->preload(),
                    Forms\Components\Select::make('tags')
                        ->relationship('tags', 'name')
                        ->multiple()
                        ->preload(),
                ])->columns(2),

            Forms\Components\Section::make('SEO')
                ->schema([
                    Forms\Components\TextInput::make('meta_title')->maxLength(512),
                    Forms\Components\Textarea::make('meta_description')->rows(3),
                    Forms\Components\TextInput::make('og_image_url')->url()->maxLength(512),
                    Forms\Components\TextInput::make('canonical_url')->url()->maxLength(512),
                    Forms\Components\Toggle::make('noindex')->label('No Index'),
                ])->columns(2)->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->sortable()->limit(60),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning'  => 'draft',
                        'success'  => 'published',
                        'primary'  => 'scheduled',
                    ]),
                Tables\Columns\TextColumn::make('author.name')->label('Author')->sortable(),
                Tables\Columns\IconColumn::make('is_featured')->boolean(),
                Tables\Columns\TextColumn::make('view_count')->sortable()->label('Views'),
                Tables\Columns\TextColumn::make('published_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['draft' => 'Draft', 'published' => 'Published', 'scheduled' => 'Scheduled']),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit'   => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}
