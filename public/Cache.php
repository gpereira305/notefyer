<?php
namespace Notefyer;

use Memcached;

class Cache
{
    private Memcached $client;

    public function __construct(string $host, int $port)
    {
        $this->client = new Memcached();
        $this->client->addServer($host, $port);
        $this->client->setOptions([
            Memcached::OPT_CONNECT_TIMEOUT => 100,
            Memcached::OPT_SEND_TIMEOUT => 100000,
            Memcached::OPT_RECV_TIMEOUT => 100000,
        ]);
    }

    public function remember(string $key, int $ttl, callable $producer)
    {
        $cached = $this->client->get($key);
        if ($cached !== false) {
            return $cached;
        }

        $value = $producer();
        if($value !== null && $value !== false) {
            $this->client->set($key, $value, $ttl);
        }
        return $value;
    }

    public function forget(string $key): void
    {
        $this->client->delete($key);
    }

    public function getStats(): array
    {
        $stats = $this->client->getStats();
        return $stats ? reset($stats) : [];
    }
}

