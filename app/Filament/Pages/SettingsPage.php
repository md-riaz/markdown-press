<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SettingsPage extends Page
{
    protected static \BackedEnum|string|null $navigationIcon  = 'heroicon-o-cog-6-tooth';
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    protected string $view            = 'filament.pages.settings';
    protected static ?string $title           = 'Site Settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'site_name'        => Setting::get('general', 'site_name', 'MarkdownPress'),
            'site_description' => Setting::get('general', 'site_description'),
            'meta_description' => Setting::get('seo', 'default_meta_description'),
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form->components([
            Forms\Components\Section::make('General')->schema([
                Forms\Components\TextInput::make('site_name')->required(),
                Forms\Components\Textarea::make('site_description')->rows(2),
            ]),
            Forms\Components\Section::make('SEO')->schema([
                Forms\Components\Textarea::make('meta_description')->rows(2)->label('Default Meta Description'),
            ]),
        ])->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set('general', 'site_name',        $data['site_name']);
        Setting::set('general', 'site_description',  $data['site_description'] ?? '');
        Setting::set('seo',     'default_meta_description', $data['meta_description'] ?? '');

        Notification::make()->title('Settings saved.')->success()->send();
    }
}
