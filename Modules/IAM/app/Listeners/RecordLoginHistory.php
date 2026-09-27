<?php

namespace Modules\IAM\Listeners;

use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Modules\IAM\Enums\LoginEvent;
use Modules\IAM\Models\LoginHistory;
use Modules\IAM\Models\User;

/**
 * Writes tenant users' sign-in events to login_histories and keeps last_login_* current.
 * Only events for the `web` guard on a tenant subdomain are recorded.
 */
class RecordLoginHistory
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly Request $request,
    ) {}

    public function handleLogin(Login $event): void
    {
        if ($event->guard !== 'web' || ! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill(['last_login_at' => now(), 'last_login_ip' => $this->request->ip()])->saveQuietly();
        $this->record(LoginEvent::Login, $event->user, $event->user->email);
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->guard === 'web' && $event->user instanceof User) {
            $this->record(LoginEvent::Logout, $event->user, $event->user->email);
        }
    }

    public function handleFailed(Failed $event): void
    {
        if ($event->guard !== 'web') {
            return;
        }

        $user = $event->user instanceof User ? $event->user : null;
        $this->record(LoginEvent::Failed, $user, $this->emailFrom($event->credentials));
    }

    public function handleLockout(Lockout $event): void
    {
        $this->record(LoginEvent::Lockout, null, $this->emailFrom($event->request->all()));
    }

    /**
     * @return array<string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Logout::class => 'handleLogout',
            Failed::class => 'handleFailed',
            Lockout::class => 'handleLockout',
        ];
    }

    private function record(LoginEvent $event, ?User $user, ?string $email): void
    {
        if (! $this->context->check()) {
            return;
        }

        LoginHistory::query()->create([
            'user_id' => $user?->id,
            'event' => $event,
            'email' => $email !== null ? mb_substr(strtolower($email), 0, 255) : null,
            'ip_address' => $this->request->ip(),
            'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 512),
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    private function emailFrom(array $values): ?string
    {
        $email = $values['email'] ?? null;

        return is_string($email) && $email !== '' ? $email : null;
    }
}
