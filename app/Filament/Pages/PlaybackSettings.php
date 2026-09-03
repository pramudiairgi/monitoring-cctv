<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;

/**
 * @property-read Schema $form
 */
class PlaybackSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Playback Settings';

    protected static ?string $title = 'Playback Settings';

    protected static ?string $slug = 'playback-settings';

    protected static ?int $navigationSort = 100;

    /**
     * Admins and operators get full edit access. This override is the
     * enforcement (navigation hiding is UX only).
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ($user->isAdmin() || $user->isOperator());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'playback_max_desktop' => (int) Setting::get('playback_max_desktop', 9),
            'playback_max_mobile_landscape' => (int) Setting::get('playback_max_mobile_landscape', 6),
            'playback_max_mobile_portrait' => (int) Setting::get('playback_max_mobile_portrait', 4),
            'playback_stagger_ms' => (int) Setting::get('playback_stagger_ms', 350),
            'playback_priority_category' => (string) Setting::get('playback_priority_category', 'patroli'),
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Auto-play limits')
                    ->description('Maximum number of streams auto-played at once, per device type.')
                    ->schema([
                        TextInput::make('playback_max_desktop')
                            ->label('Max auto-play (desktop)')
                            ->numeric()
                            ->integer()
                            ->required()
                            ->minValue(1)
                            ->maxValue(25),
                        TextInput::make('playback_max_mobile_landscape')
                            ->label('Max auto-play (mobile landscape)')
                            ->numeric()
                            ->integer()
                            ->required()
                            ->minValue(1)
                            ->maxValue(25),
                        TextInput::make('playback_max_mobile_portrait')
                            ->label('Max auto-play (mobile portrait)')
                            ->numeric()
                            ->integer()
                            ->required()
                            ->minValue(1)
                            ->maxValue(25),
                    ])
                    ->columns(3),
                Section::make('Playback behavior')
                    ->schema([
                        TextInput::make('playback_stagger_ms')
                            ->label('Stagger delay (ms)')
                            ->numeric()
                            ->integer()
                            ->required()
                            ->minValue(0)
                            ->maxValue(5000),
                        TextInput::make('playback_priority_category')
                            ->label('Priority category')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Category slug played first, e.g. patroli.'),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save settings')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            Setting::set($key, $value);
        }

        Notification::make()
            ->title('Playback settings saved')
            ->success()
            ->send();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment(Alignment::Start)
                    ->key('form-actions'),
            ]);
    }
}
