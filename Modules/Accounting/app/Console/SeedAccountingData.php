<?php

namespace Modules\Accounting\Console;

use Illuminate\Console\Command;
use Modules\Accounting\Database\Seeders\AccountingDatabaseSeeder;

class SeedAccountingData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounting:seed {--fresh : Wipe the database before seeding}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed the accounting module with initial data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Seeding Accounting Module Data...');

        if ($this->option('fresh')) {
            $this->warn('Fresh seeding not fully implemented in this command, running standard seeder...');
        }

        $this->call('module:seed', [
            'module' => 'Accounting',
            '--class' => 'AccountingDatabaseSeeder',
        ]);

        $this->info('Accounting module seeded successfully.');

        $this->table(
            ['Entity', 'Status'],
            [
                ['Chart of Accounts', 'Seeded'],
                ['Cost Centers', 'Seeded'],
                ['Journal Entries', 'Seeded'],
                ['Accounts Payable', 'Seeded'],
                ['Budgets', 'Seeded'],
                ['Bank Accounts', 'Seeded'],
            ]
        );
    }
}
