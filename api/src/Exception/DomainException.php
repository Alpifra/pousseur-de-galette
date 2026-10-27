<?php

namespace App\Exception;

/**
 * A business rule rejected the request. The error code is part of the API
 * contract: the front uses it to pick the message it shows to the guest.
 */
class DomainException extends \RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $statusCode = 422,
        /** @var array<string, int|string|bool> */
        public readonly array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function eventNotLive(): self
    {
        return new self('event_not_live', "L'événement n'accepte pas de proposition pour le moment.", 409);
    }

    public static function eventEnded(): self
    {
        return new self('event_ended', 'La soirée est terminée.', 409);
    }

    public static function guestBanned(): self
    {
        return new self('guest_banned', 'Tu ne peux plus participer à cet événement.', 403);
    }

    public static function notFound(string $what): self
    {
        return new self('not_found', \sprintf('%s introuvable.', $what), 404);
    }

    public static function invalidSession(): self
    {
        return new self('invalid_session', 'Session convive inconnue ou expirée.', 401);
    }

    public static function quotaExceeded(int $max): self
    {
        return new self('quota_exceeded', \sprintf('Tu as déjà %d galettes en attente.', $max), 429, ['max' => $max]);
    }

    public static function explicitBlocked(): self
    {
        return new self('explicit_blocked', 'Les titres explicites sont bloqués sur cette soirée.');
    }

    public static function trackTooLong(int $maxMs): self
    {
        return new self('track_too_long', \sprintf('Ce titre dépasse la durée maximale (%d min).', intdiv($maxMs, 60_000)), 422, ['maxMs' => $maxMs]);
    }

    public static function replayDelay(int $minutes): self
    {
        return new self('replay_delay', \sprintf('Ce titre vient de passer, patiente %d min.', $minutes), 409, ['minutes' => $minutes]);
    }

    public static function itemLocked(): self
    {
        return new self('item_locked', 'Ce titre est verrouillé : il passe juste après.', 409);
    }

    public static function downvotesDisabled(): self
    {
        return new self('downvotes_disabled', 'Les votes négatifs sont désactivés sur cette soirée.', 403);
    }

    public static function ownProposal(): self
    {
        return new self('own_proposal', 'Tu ne peux pas voter pour ta propre galette, mais tu peux la retirer.', 403);
    }

    public static function trackNotFound(): self
    {
        return new self('track_not_found', 'Ce titre est introuvable sur Spotify.', 404);
    }

    public static function spotifyUnavailable(string $reason): self
    {
        return new self('spotify_unavailable', $reason, 503);
    }

    /** Same contract, but the technical cause stays in the logs: guests must not read it. */
    public static function spotifyUnreachable(\Throwable $cause): self
    {
        return new self('spotify_unavailable', 'Spotify ne répond pas, réessaie dans un instant.', 503, [], $cause);
    }
}
