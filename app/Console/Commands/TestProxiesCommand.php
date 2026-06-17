<?php

namespace App\Console\Commands;

use App\Models\Proxy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestProxiesCommand extends Command
{
    protected $signature = 'proxies:test {--url=https://yandex.ru : URL to test proxy against} {--limit=10 : Max proxies to test}';

    protected $description = 'Test active proxies by making a request to Yandex';

    public function handle(): int
    {
        $url = $this->option('url');
        $limit = (int) $this->option('limit');

        $proxies = Proxy::where('is_active', true)
            ->orderBy('fails_count', 'asc')
            ->orderBy('last_used_at', 'asc nulls first')
            ->limit($limit * 3)
            ->get();

        if ($proxies->isEmpty()) {
            $this->error('No active proxies found. Run php artisan proxies:fetch first.');
            return self::FAILURE;
        }

        $this->info("Testing {$proxies->count()} proxies against {$url}...");
        $working = 0;

        foreach ($proxies as $proxy) {
            if ($working >= $limit) {
                break;
            }

            $start = microtime(true);
            try {
                $response = Http::timeout(5)
                    ->connectTimeout(3)
                    ->withOptions([
                        'proxy' => $proxy->server,
                        'verify' => false,
                    ])
                    ->get($url);

                $elapsed = round((microtime(true) - $start) * 1000);

                if ($response->successful()) {
                    $body = $response->body();
                    $hasCaptcha = str_contains($body, 'showcaptcha') || str_contains($body, 'captcha');
                    if ($hasCaptcha) {
                        $this->warn("  {$proxy->server} - OK but captcha ({$elapsed}ms)");
                    } else {
                        $this->info("  {$proxy->server} - WORKING ({$elapsed}ms)");
                        $working++;
                    }
                } else {
                    $this->error("  {$proxy->server} - HTTP {$response->status()} ({$elapsed}ms)");
                }
            } catch (\Throwable $e) {
                $this->error("  {$proxy->server} - FAILED: " . class_basename($e));
            }
        }

        $this->newLine();
        $this->info("Found {$working} working proxies out of {$proxies->count()} tested.");

        return $working > 0 ? self::SUCCESS : self::FAILURE;
    }
}
