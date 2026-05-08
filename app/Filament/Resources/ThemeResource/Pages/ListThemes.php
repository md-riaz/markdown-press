<?php

namespace App\Filament\Resources\ThemeResource\Pages;

use App\Filament\Resources\ThemeResource;
use App\Modules\Theme\ThemeRegistry;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListThemes extends ListRecords
{
    protected static string $resource = ThemeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('syncThemes')
                ->label('Sync themes from disk')
                ->icon('heroicon-o-arrow-path')
                ->action(function (): void {
                    $count = app(ThemeRegistry::class)->discover()->count();

                    Notification::make()
                        ->title("Synced {$count} theme(s).")
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
