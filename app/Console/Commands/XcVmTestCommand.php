<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\XcVm\XcVmSyncService;
use Illuminate\Console\Command;

class XcVmTestCommand extends Command
{
    protected $signature = 'xcvm:test';

    protected $description = 'Verify the XC-VM connection and API key';

    public function handle(XcVmSyncService $service): int
    {
        $state = $service->testConnection();

        $this->line('XC-VM connection test:');

        $this->newLine();

        foreach ([
            'configured' => $state['configured'] ? 'yes' : 'no',
            'api base url' => $state['url'] ?? '(not configured)',
        ] as $label => $value) {
            $this->line(sprintf('  %-14s %s', $label, $value));
        }

        $this->newLine();

        $diag = $state['diagnostics'];

        if (($diag['ok'] ?? false) === true) {
            $this->info('  Connection OK — XC-VM is reachable and the API key works.');
            $this->line('  Admin user: ' . json_encode($diag['detail']));

            return self::SUCCESS;
        }

        $this->error('  ' . ($diag['error'] ?? 'Unknown error'));
        if (! empty($diag['detail'])) {
            $this->line('  Response: ' . json_encode($diag['detail']));
        }

        return self::FAILURE;
    }
}