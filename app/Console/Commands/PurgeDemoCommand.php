<?php

namespace App\Console\Commands;

use App\Actions\Demo\PurgeDemoAccount;
use Illuminate\Console\Command;

class PurgeDemoCommand extends Command
{
    protected $signature = 'noctua:demo:purge';

    protected $description = 'Borra las cuentas de prueba caducadas con todos sus libros y archivos';

    public function handle(PurgeDemoAccount $purge): int
    {
        $count = $purge->expired();
        $this->components->info("Cuentas de prueba borradas: {$count}.");

        return self::SUCCESS;
    }
}
