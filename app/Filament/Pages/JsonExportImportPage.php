<?php
namespace App\Filament\Pages;

use App\Services\JsonExporter;
use App\Services\JsonImporter;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JsonExportImportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    protected static \BackedEnum|string|null $navigationIcon  = 'heroicon-o-arrow-path';
    protected static ?string $title           = 'JSON Export / Import';
    protected string  $view            = 'filament.pages.json-export-import';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('json_file')
                    ->label('JSON File')
                    ->acceptedFileTypes(['application/json', 'text/plain'])
                    ->required(false),
                Select::make('mode')
                    ->options(['append' => 'Append', 'fresh' => 'Fresh (delete all)'])
                    ->default('append'),
            ])
            ->statePath('data');
    }

    public function exportData(): StreamedResponse
    {
        $data = app(JsonExporter::class)->export();
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return response()->streamDownload(fn () => print($json), 'posts-export.json', [
            'Content-Type' => 'application/json',
        ]);
    }

    public function importData(): void
    {
        $state = $this->form->getState();

        try {
            $uploaded = $state['json_file'] ?? null;
            if (!$uploaded) {
                Notification::make()->title('No file selected.')->warning()->send();
                return;
            }
            if (is_array($uploaded)) $uploaded = reset($uploaded);

            \Illuminate\Support\Facades\Storage::copy($uploaded, 'json-import-temp.json');
            $jsonPath = storage_path('app/json-import-temp.json');
            $data = json_decode(file_get_contents($jsonPath), true) ?? [];

            $result = app(JsonImporter::class)->import($data, $state['mode'] ?? 'append');

            Notification::make()
                ->title("Import done: {$result['imported']} imported, {$result['skipped']} skipped")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Import failed: ' . $e->getMessage())->danger()->send();
        }
    }
}
