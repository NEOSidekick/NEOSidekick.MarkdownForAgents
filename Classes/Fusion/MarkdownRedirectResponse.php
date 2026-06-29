<?php

declare(strict_types=1);

namespace NEOSidekick\MarkdownForAgents\Fusion;

use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 */
final class MarkdownRedirectResponse
{
    public function __construct(
        private readonly ResponseInterface $response,
        private readonly string $canonicalUri = ''
    ) {
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    public function getCanonicalUri(): string
    {
        return $this->canonicalUri;
    }

    public function __toString(): string
    {
        return '';
    }
}
