<?php

namespace App\Tests\Unit;

use App\Spotify\SpotifyClient;
use App\Spotify\SpotifyException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class SpotifyClientTest extends TestCase
{
    public function testAPlayerCommandAnsweredWithANonJsonBodySucceeds(): void
    {
        $client = $this->client(new MockResponse('spotify:track:4uLU6hMCjMI75M1A2tKUQC', ['http_code' => 200]));

        self::assertNull($client->request('POST', '/me/player/queue', 'token', ['uri' => 'spotify:track:4uLU6hMCjMI75M1A2tKUQC']));
    }

    public function testAReadAnsweredWithInvalidJsonIsASpotifyError(): void
    {
        $client = $this->client(new MockResponse('<html>', ['http_code' => 200]));

        $this->expectException(SpotifyException::class);
        $client->request('GET', '/me/player', 'token');
    }

    public function testAReadIsDecoded(): void
    {
        $client = $this->client(new MockResponse('{"is_playing":true}', ['http_code' => 200]));

        self::assertSame(['is_playing' => true], $client->request('GET', '/me/player', 'token'));
    }

    private function client(MockResponse $response): SpotifyClient
    {
        return new SpotifyClient(new MockHttpClient($response), new NullLogger());
    }
}
