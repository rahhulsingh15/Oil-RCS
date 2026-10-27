<?php

namespace App\Console\Commands;

use App\Support\Permissions;
use Illuminate\Console\Command;

class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync';

    protected $description = 'Sync the permissions table with config/permissions.php and create the built-in roles';

    public function handle(): int
    {
        $result = Permissions::sync();

        $this->info("Permissions synced: {$result['created']} added, {$result['removed']} removed, ".count(Permissions::keys()).' total.');

        return self::SUCCESS;
    }
}
