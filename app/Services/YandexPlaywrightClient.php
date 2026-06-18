<?php

namespace App\Services;

use App\Exceptions\ParseException;
use App\Models\Proxy;
use Symfony\Component\Process\Process;

class YandexPlaywrightClient
{
    /**
     * Run the external Node.js Playwright scraper process and parse its JSON output.
     *
     * @throws ParseException
     */
    public function scrape(string $url, ?Proxy $proxy = null): array
    {
        $scriptPath = base_path('parser/parse.js');

        $args = ['node', $scriptPath, $url];
        if ($proxy) {
            $args[] = $proxy->server;
        }

        $process = new Process($args);

        // Set working directory to the parser folder to ensure Node resolves modules
        $process->setWorkingDirectory(base_path('parser'));
        $process->setTimeout(6000); // 600 seconds timeout for loading 600 reviews

        $process->run();

        if (!$process->isSuccessful()) {
            throw new ParseException('Scraper process failed: ' . $process->getErrorOutput());
        }

        $output = $process->getOutput();
        $result = json_decode($output, true);

        if (!$result || !isset($result['success'])) {
            throw new ParseException('Invalid scraper JSON output: ' . substr($output, 0, 500));
        }

        if (!$result['success']) {
            throw new ParseException('Scraper reported failure: ' . ($result['error'] ?? 'Unknown error'));
        }

        return $result;
    }
}
