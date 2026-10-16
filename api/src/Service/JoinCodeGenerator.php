<?php

namespace App\Service;

use App\Repository\EventRepository;

class JoinCodeGenerator
{
    /** No 0/O/1/I/L: the code is read out loud and typed on a phone. */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    private const LENGTH = 6;

    public function __construct(private EventRepository $events)
    {
    }

    public function generate(): string
    {
        for ($attempt = 0; $attempt < 20; ++$attempt) {
            $code = '';
            for ($i = 0; $i < self::LENGTH; ++$i) {
                $code .= self::ALPHABET[random_int(0, \strlen(self::ALPHABET) - 1)];
            }

            if (!$this->events->joinCodeExists($code)) {
                return $code;
            }
        }

        throw new \RuntimeException('Unable to generate a free join code.');
    }
}
