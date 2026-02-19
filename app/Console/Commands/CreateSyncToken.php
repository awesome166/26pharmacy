<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateSyncToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:token {email : The email of the user to attach the token to} {--name=SyncDevice : The name of the token}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a long-lived API token for Sync operations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $tokenName = $this->option('name');

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User not found with email: {$email}");
            return 1;
        }

        // Create token with all abilities or specific scope if desired
        $token = $user->createToken($tokenName, ['sync:ops']);

        $this->info("Token created successfully for user: {$user->name}");
        $this->line("Token Name: {$tokenName}");
        $this->line("Plain Text Token (Save this, it won't be shown again):");
        $this->warn($token->plainTextToken);

        return 0;
    }
}
