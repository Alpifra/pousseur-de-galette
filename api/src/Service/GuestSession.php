<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\Guest;
use App\Exception\DomainException;
use App\Repository\GuestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * Guests have no account: their identity is a random token in an http-only cookie,
 * checked against the guest row. Nothing personal is stored beyond the pseudo.
 */
class GuestSession
{
    public const COOKIE_NAME = 'pdg_guest';
    private const LIFETIME = 2_592_000; // 30 days
    private const TOUCH_INTERVAL = 60;

    public function __construct(
        private GuestRepository $guests,
        private EntityManagerInterface $em,
    ) {
    }

    public static function createToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function cookie(Guest $guest): Cookie
    {
        return Cookie::create(self::COOKIE_NAME, $guest->getSessionToken())
            ->withExpires(time() + self::LIFETIME)
            ->withPath('/')
            ->withSecure(true)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX);
    }

    public function clearCookie(): Cookie
    {
        return Cookie::create(self::COOKIE_NAME)->withExpires(1)->withPath('/');
    }

    public function resolve(Request $request, ?Event $event = null): ?Guest
    {
        $token = $request->cookies->get(self::COOKIE_NAME);
        if (!\is_string($token) || '' === $token) {
            return null;
        }

        $guest = $this->guests->findOneBySessionToken($token);
        if (null === $guest) {
            return null;
        }

        if (null !== $event && !$guest->getEvent()->getId()->equals($event->getId())) {
            return null;
        }

        if (time() - $guest->getLastSeenAt()->getTimestamp() > self::TOUCH_INTERVAL) {
            $guest->touch();
            $this->em->flush();
        }

        return $guest;
    }

    public function require(Request $request, ?Event $event = null): Guest
    {
        $guest = $this->resolve($request, $event);
        if (null === $guest) {
            throw DomainException::invalidSession();
        }

        if ($guest->isBanned()) {
            throw DomainException::guestBanned();
        }

        return $guest;
    }
}
