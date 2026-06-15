<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Services\YandexParserService;
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
     *
     * @var int
     */
    public $timeout = 180;

    protected Organization $organization;

    /**
     * Create a new job instance.
     */
    public function __construct(Organization $organization)
    {
        $this->organization = $organization;
    }

    /**
     * Execute the job.
     */
    public function handle(YandexParserService $parserService): void
    {
        try {
            $parserService->parse($this->organization);
        } catch (Exception $e) {
            // Re-throw to mark job as failed
            throw $e;
        }
    }
}
