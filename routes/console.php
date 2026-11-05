<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:make-admin {email}', function (): void {
    $user = User::where('email', $this->argument('email'))->first();
    if (! $user) {
        $this->error('Account not found. Register an account first.');

        return;
    }
    $user->role = 'admin';
    $user->save();
    $this->info('Administrator access enabled for '.$user->email.'.');
})->purpose('Grant administrator access to an existing account');
