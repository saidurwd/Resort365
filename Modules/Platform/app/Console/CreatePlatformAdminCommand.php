<?php

namespace Modules\Platform\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Modules\Platform\Models\PlatformAdmin;

#[Signature('platform:create-admin {email : Sign-in email} {name : Full name}')]
#[Description('Create a platform super admin (prompts for the password)')]
class CreatePlatformAdminCommand extends Command
{
    public function handle(): int
    {
        $data = [
            'email' => strtolower((string) $this->argument('email')),
            'name' => (string) $this->argument('name'),
            'password' => (string) $this->secret('Password'),
        ];

        $validator = Validator::make($data, [
            'email' => ['required', 'email', 'max:255', 'unique:platform_admins,email'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        PlatformAdmin::query()->create($data);

        $this->components->info("Platform admin [{$data['email']}] created. Sign in at ".route('platform.login'));

        return self::SUCCESS;
    }
}
