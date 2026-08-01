<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/** Console command that creates (or promotes) the initial admin account. */
class CreateAdminCommand extends Command
{
    protected $signature = 'app:create-admin
        {email? : The admin email address}
        {name? : The admin display name}
        {--password= : The password (min 12 chars). Generated if omitted.}';

    protected $description = 'Create (or promote) the initial admin user.';

    /** Create or promote the admin from the prompted credentials. */
    public function handle(): int
    {
        $email = $this->argument('email') ?: $this->ask('Email');
        $name = $this->argument('name') ?: $this->ask('Display name (leave blank to generate one)');
        $password = $this->option('password') ?: Str::password(16);

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password],
            [
                'email' => ['required', 'email'],
                'name' => ['nullable', 'string', 'max:255'],
                'password' => ['required', Password::min(12)],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        // Everyone needs a display name; generate one from the email when blank.
        if (blank($name)) {
            $name = User::generateDisplayName($email);
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'password' => Hash::make($password),
            'is_admin' => true,
            'is_locked' => false,
            'must_change_password' => false,
        ])->save();

        $this->info("Admin user ready: {$user->email}");

        if (! $this->option('password')) {
            $this->line("Generated password: {$password}");
        }

        return self::SUCCESS;
    }
}
