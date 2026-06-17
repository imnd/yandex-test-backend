<?php

namespace App\Console\Commands;

use App\Models\Proxy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchProxiesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'proxies:fetch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch and update proxy servers list from external sources';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting proxy fetch...');

        $sources = [
            [
                'url' => 'https://api.proxyscrape.com/v2/?request=displayproxies&protocol=http&timeout=10000&country=all&ssl=all&anonymity=all',
                'protocol' => 'http',
            ],
            [
                'url' => 'https://api.proxyscrape.com/v2/?request=displayproxies&protocol=socks5&timeout=10000&country=all&ssl=all&anonymity=all',
                'protocol' => 'socks5',
            ],
            [
                'url' => 'https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/socks5.txt',
                'protocol' => 'socks5',
            ],
            [
                'url' => 'https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/http.txt',
                'protocol' => 'http',
            ],
        ];

        $imported = 0;
        $totalFound = 0;

        // Prune failed proxies first to make space
        $pruned = Proxy::where('fails_count', '>=', 3)->delete();
        if ($pruned > 0) {
            $this->info("Pruned {$pruned} failed/unreliable proxies from the database.");
            Log::info("Pruned {$pruned} failed proxies.");
        }

        foreach ($sources as $source) {
            try {
                $this->info("Fetching from: {$source['url']}...");
                $response = Http::timeout(15)->get($source['url']);

                if (!$response->successful()) {
                    $this->error("Failed to fetch from source: {$source['url']}");
                    continue;
                }

                $text = $response->body();
                $lines = explode("\n", $text);
                $protocol = $source['protocol'];

                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) {
                        continue;
                    }

                    // Validate IP:PORT format
                    if (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d{1,5}$/', $line)) {
                        $totalFound++;
                        $server = "{$protocol}://{$line}";

                        // Update or insert the proxy, making sure to reset fails count and set active to true
                        Proxy::updateOrCreate(
                            ['server' => $server],
                            [
                                'is_active' => true,
                                'fails_count' => 0,
                            ]
                        );
                        $imported++;
                    }
                }
            } catch (\Exception $e) {
                $this->error("Error fetching from {$source['url']}: " . $e->getMessage());
                Log::error("Failed fetching proxies from {$source['url']}: " . $e->getMessage());
            }
        }

        $this->info("Successfully imported/updated {$imported} proxies (found {$totalFound} total).");
        Log::info("Proxies update completed: {$imported} active proxies in DB.");

        // Keep database size sane: if total active proxies exceeds 2000, prune the oldest ones
        $count = Proxy::count();
        if ($count > 2000) {
            $excess = $count - 2000;
            Proxy::orderBy('last_used_at', 'asc')
                ->limit($excess)
                ->delete();
            $this->info("Cleaned up {$excess} excess older proxies to keep database optimal.");
        }

        return self::SUCCESS;
    }
}
