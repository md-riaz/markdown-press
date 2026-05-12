<?php
namespace App\Filament\Resources\ApiTokenResource\Pages;

use App\Filament\Resources\ApiTokenResource;
use App\Models\ApiToken;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;

class ListApiTokens extends ListRecords
{
    protected static string $resource = ApiTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createToken')
                ->label('Create Token')
                ->form([
                    TextInput::make('name')->required(),
                    TextInput::make('expires_in_days')->numeric()->default(365)->label('Expires In (days)'),
                ])
                ->action(function (array $data): void {
                    $rawToken = Str::random(64);
                    $user     = User::where('role', 'admin')->first();

                    ApiToken::create([
                        'user_id'    => $user?->id ?? auth()->id(),
                        'name'       => $data['name'],
                        'token'      => hash('sha256', $rawToken),
                        'expires_at' => now()->addDays((int) ($data['expires_in_days'] ?? 365)),
                    ]);

                    Notification::make()
                        ->title("Token created. Raw token (shown once): {$rawToken}")
                        ->success()
                        ->send();
                }),
        ];
    }
}
