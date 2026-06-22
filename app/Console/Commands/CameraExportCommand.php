<?php

namespace App\Console\Commands;

use App\Services\CameraExport;
use Illuminate\Console\Command;

class CameraExportCommand extends Command
{
    protected $signature = 'cameras:export';

    protected $description = 'Export cameras and categories to JSON file';

    public function handle(): int
    {
        app(CameraExport::class)->handle();

        $this->info('Cameras exported successfully to storage/app/public/cameras.json');

        return Command::SUCCESS;
    }
}
