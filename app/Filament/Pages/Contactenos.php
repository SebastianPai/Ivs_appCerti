<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Contactenos\ContactenosResource;
use App\Models\MensajeContacto;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Contáctenos: cualquier usuario le escribe a la certificadora desde el menú de su cuenta.
 * El mensaje llega a la bandeja Administrador › Contactenos y a la campanita de los admin.
 */
class Contactenos extends Page
{
    protected string $view = 'filament.pages.contactenos';

    protected static ?string $slug = 'contactenos';

    protected static ?string $title = 'Contáctenos';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Escríbanos')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->description('Le responderemos al correo de su cuenta: '.Auth::user()?->email)
                    ->schema([
                        TextInput::make('asunto')
                            ->label('Asunto')
                            ->required()
                            ->maxLength(150),
                        Textarea::make('mensaje')
                            ->label('Mensaje')
                            ->required()
                            ->minLength(10)
                            ->maxLength(2000)
                            ->rows(6),
                    ]),
            ])
            ->statePath('data');
    }

    public function enviar(): void
    {
        $user = Auth::user();
        $limite = 'contactenos:'.$user->id;

        if (RateLimiter::tooManyAttempts($limite, 5)) {
            Notification::make()->title('Ya envió varios mensajes')->body('Espere unos minutos antes de enviar otro.')->warning()->send();

            return;
        }

        $datos = $this->form->getState();
        RateLimiter::hit($limite, 600);

        $mensaje = MensajeContacto::create([
            'user_id' => $user->id,
            'nombre' => $user->name,
            'email' => $user->email,
            'asunto' => $datos['asunto'],
            'mensaje' => $datos['mensaje'],
        ]);

        Notification::make()
            ->title('Nuevo mensaje de Contáctenos')
            ->body("{$user->name}: {$mensaje->asunto}")
            ->icon('heroicon-o-chat-bubble-left-right')
            ->actions([Action::make('abrir')->label('Abrir')->url(ContactenosResource::getUrl())->markAsRead()])
            ->sendToDatabase(User::role('admin')->get());

        $this->form->fill();

        Notification::make()->title('Buen trabajo!')->body('Su mensaje fue enviado. Pronto nos pondremos en contacto.')->success()->send();
    }
}
