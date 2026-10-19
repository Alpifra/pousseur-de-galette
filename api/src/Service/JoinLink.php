<?php

namespace App\Service;

use App\Entity\Event;
use Symfony\Component\Routing\RequestContext;

/** The join page is served by the front, not by a Symfony route: its URL is built from the request context. */
class JoinLink
{
    public function __construct(private RequestContext $context)
    {
    }

    public function url(Event $event): string
    {
        $scheme = $this->context->getScheme();
        $authority = $this->context->getHost();
        $port = 'https' === $scheme ? $this->context->getHttpsPort() : $this->context->getHttpPort();
        if (('https' === $scheme && 443 !== $port) || ('http' === $scheme && 80 !== $port)) {
            $authority .= ':'.$port;
        }

        return $scheme.'://'.$authority.$this->context->getBaseUrl().'/e/'.$event->getJoinCode();
    }
}
