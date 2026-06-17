<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Services\YandexParsingOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Exception;

class ParseOrganizationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 180;

    public int $tries = 1;

    protected Organization $organization;

    public function __construct(Organization $organization)
    {
        $this->organization = $organization;
    }

    public function handle(YandexParsingOrchestrator $orchestrator): void
    {
        $orchestrator->parse($this->organization);
    }
}
