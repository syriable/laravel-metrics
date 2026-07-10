<?php

namespace Syriable\Metrics\Commands;

use Illuminate\Console\Command;

class MetricsCommand extends Command
{
    public $signature = 'laravel-metrics';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
