<?php

namespace App\Filament\Resources\SubscriberResource\Pages;

use App\Filament\Resources\SubscriberResource;
use App\Models\Subscriber;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListSubscribers extends ListRecords
{
    protected static string $resource = SubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn () => $this->exportCsv()),
            Actions\CreateAction::make(),
        ];
    }

    public function exportCsv(): StreamedResponse
    {
        $filename = 'subscribers-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, ['Name', 'Email', 'Status', 'Subscribed At', 'Unsubscribed At']);

            Subscriber::query()
                ->orderByDesc('subscribed_at')
                ->orderBy('email')
                ->each(function (Subscriber $subscriber) use ($handle): void {
                    fputcsv($handle, [
                        $this->sanitizeCsvValue($subscriber->name),
                        $this->sanitizeCsvValue($subscriber->email),
                        $subscriber->status,
                        $subscriber->subscribed_at?->toDateTimeString(),
                        $subscriber->unsubscribed_at?->toDateTimeString(),
                    ]);
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function sanitizeCsvValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return preg_match('/^[=\-+@]/', $value) ? "'".$value : $value;
    }
}
