<?php

namespace Database\Seeders;

use App\Models\Proxy;
use Illuminate\Database\Seeder;

class ProxySeeder extends Seeder
{
    public function run(): void
    {
        $rawProxies = [
            '91.203.242.66:222',
            '188.43.32.130:8083',
            '202.49.176.24:2080',
            '185.135.81.149:9060',
            '78.109.34.192:8080',
            '5.101.5.160:2080',
            '31.28.4.192:80',
            '194.87.196.108:3128',
            '81.177.160.200:80',
            '89.151.133.216:8080',
            '195.9.238.36:8080',
            '89.145.145.176:1080',
            '176.12.71.36:1234',
            '188.68.205.126:1080',
            '45.143.94.111:1080',
            '178.130.46.234:10801',
            '62.113.119.32:1080',
            '45.8.88.236:1080',
            '176.12.66.148:1080',
            '178.47.142.252:1080',
            '85.30.219.207:80',
            '91.236.238.103:8080',
            '176.12.71.36:1234',
            '81.177.74.135:8081',
            '212.113.107.128:36613',
            '185.21.141.238:1080',
            '130.49.150.81:1080',
            '89.17.35.212:8080',
            '45.87.140.155:8080',
            '195.190.107.62:3389'
        ];

        foreach ($rawProxies as $rawProxy) {
            $parts = explode(':', $rawProxy);
            if (count($parts) === 2) {
                $port = (int)$parts[1];
                // Ports like 1080, 10801 are typically socks5. Others default to http
                $protocol = ($port === 1080 || $port === 10801) ? 'socks5' : 'http';
                $server = "$protocol://$rawProxy";

                Proxy::updateOrCreate(compact('server'));
            }
        }
    }
}
