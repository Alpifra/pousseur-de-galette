<?php

namespace App\Tests\Unit;

use App\Service\TokenCipher;
use PHPUnit\Framework\TestCase;

class TokenCipherTest extends TestCase
{
    public function testRoundTrip(): void
    {
        $cipher = new TokenCipher(bin2hex(random_bytes(32)));
        $encrypted = $cipher->encrypt('AQD-spotify-refresh-token');

        self::assertNotSame('AQD-spotify-refresh-token', $encrypted);
        self::assertSame('AQD-spotify-refresh-token', $cipher->decrypt($encrypted));
    }

    public function testCiphertextDiffersOnEachCall(): void
    {
        $cipher = new TokenCipher(bin2hex(random_bytes(32)));

        self::assertNotSame($cipher->encrypt('same'), $cipher->encrypt('same'));
    }

    public function testAnotherKeyCannotDecrypt(): void
    {
        $encrypted = (new TokenCipher(bin2hex(random_bytes(32))))->encrypt('secret');

        $this->expectException(\RuntimeException::class);

        (new TokenCipher(bin2hex(random_bytes(32))))->decrypt($encrypted);
    }

    public function testRejectsAKeyOfTheWrongSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TokenCipher(bin2hex(random_bytes(8)));
    }
}
