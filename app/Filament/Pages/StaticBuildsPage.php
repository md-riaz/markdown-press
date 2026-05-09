<?php
namespace App\Filament\Pages;

use App\Models\StaticBuild;
use App\Models\Theme;
use App\Modules\StaticGen\BuildOrchestrator;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaticBuildsPage extends Page implements HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    protected static \BackedEnum|string|null $navigationIcon  = 'heroicon-o-archive-box';
    protected static ?string $title           = 'Static Builds';
    protected string  $view            = 'filament.pages.static-builds';

    public function triggerBuild(): void
    {
        try {
            $theme = Theme::where('is_active', true)->firstOrFail();
            $build = app(BuildOrchestrator::class)->build($theme);

            Notification::make()
                ->title("Build #{$build->id} completed ({$build->file_count} files)")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Build failed: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(StaticBuild::query()->latest())
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('theme.name')->label('Theme'),
                TextColumn::make('status')->badge()
                    ->color(fn ($state) => match ($state) {
                        'completed' => 'success',
                        'failed'    => 'danger',
                        default     => 'warning',
                    }),
                TextColumn::make('file_count')->label('Files'),
                TextColumn::make('zip_size')->label('ZIP Size')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / 1024, 1) . ' KB' : '-'),
                TextColumn::make('started_at')->dateTime()->label('Started'),
                TextColumn::make('completed_at')->dateTime()->label('Completed'),
            ]);
    }

    public function download(int $buildId): StreamedResponse
    {
        $build = StaticBuild::findOrFail($buildId);
        abort_unless($build->zip_path, 404);

        return Storage::response($build->zip_path, "build_{$buildId}.zip");
    }
}
