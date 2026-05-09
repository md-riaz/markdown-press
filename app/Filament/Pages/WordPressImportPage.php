<?php
namespace App\Filament\Pages;

use App\Services\WordPressImporter;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class WordPressImportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    protected static \BackedEnum|string|null $navigationIcon  = 'heroicon-o-arrow-down-tray';
    protected static ?string $title           = 'WordPress Import';
    protected string  $view            = 'filament.pages.wordpress-import';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('xml_file')
                    ->label('WXR XML File')
                    ->acceptedFileTypes(['text/xml', 'application/xml'])
                    ->required(),
                Select::make('mode')
                    ->options(['append' => 'Append', 'fresh' => 'Fresh (delete all)'])
                    ->default('append')
                    ->required(),
            ])
            ->statePath('data');
    }

    public function import(): void
    {
        $data    = $this->form->getState();

        try {
            $uploaded = $data['xml_file'];
            if (is_array($uploaded)) $uploaded = reset($uploaded);

            \Illuminate\Support\Facades\Storage::copy($uploaded, 'wp-import-temp.xml');
            $xmlPath = storage_path('app/wp-import-temp.xml');

            $importer = app(WordPressImporter::class);
            $result   = $importer->import($xmlPath, $data['mode'] ?? 'append');

            Notification::make()
                ->title("Import done: {$result['posts']} imported, {$result['skipped']} skipped")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Import failed: ' . $e->getMessage())->danger()->send();
        }
    }
}
