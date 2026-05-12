<?php
namespace App\Filament\Pages;

use App\Models\Post;
use App\Modules\AI\AiDriverManager;
use App\Modules\AI\Jobs\GenerateExcerptJob;
use App\Modules\AI\Jobs\GenerateSummaryJob;
use App\Modules\AI\Jobs\TranslatePostJob;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class AiToolsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static \UnitEnum|string|null $navigationGroup = 'Content';
    protected static \BackedEnum|string|null $navigationIcon  = 'heroicon-o-cpu-chip';
    protected static ?string $title           = 'AI Tools';
    protected string  $view            = 'filament.pages.ai-tools';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('post_id')
                    ->label('Post')
                    ->options(Post::published()->pluck('title', 'id'))
                    ->required(),
                Select::make('driver')
                    ->options(['gemini' => 'Gemini', 'qwen' => 'Qwen', 'openai' => 'OpenAI', 'anthropic' => 'Anthropic'])
                    ->required(),
                Select::make('action')
                    ->options(['summarize' => 'Summarize', 'excerpt' => 'Excerpt', 'translate' => 'Translate'])
                    ->required()
                    ->reactive(),
                TextInput::make('target_locale')
                    ->label('Target Locale')
                    ->visible(fn ($get) => $get('action') === 'translate'),
            ])
            ->statePath('data');
    }

    public function runAction(): void
    {
        $data   = $this->form->getState();
        $post   = Post::findOrFail($data['post_id']);

        try {
            $manager = app(AiDriverManager::class);
            $driver  = $manager->driver($data['driver']);

            match ($data['action']) {
                'summarize' => dispatch(new GenerateSummaryJob($post)),
                'excerpt'   => dispatch(new GenerateExcerptJob($post)),
                'translate' => dispatch(new TranslatePostJob($post, $data['target_locale'] ?? 'en')),
            };

            Notification::make()->title('Job dispatched successfully.')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Error: ' . $e->getMessage())->danger()->send();
        }
    }
}
