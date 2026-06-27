<?php

declare(strict_types=1);

namespace NEOSidekick\MarkdownForAgents\Fusion;

use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\Response;
use Neos\Fusion\FusionObjects\HttpResponseImplementation;
use Psr\Http\Message\ResponseInterface;

final class MarkdownHttpResponseImplementation extends HttpResponseImplementation
{
    public function evaluate(): string
    {
        if (!in_array($this->getResponseHeadName(), $this->ignoreProperties, true)) {
            $this->ignoreProperties[] = $this->getResponseHeadName();
        }

        $response = $this->getResponseHead();
        if (!$response instanceof ResponseInterface) {
            throw new \InvalidArgumentException('Could not render HTTP response because the response head was not a valid HTTP response object.', 1557932997);
        }

        $resultParts = $this->evaluateNestedProperties();
        foreach ($resultParts as $resultPart) {
            if ($resultPart instanceof MarkdownRedirectResponse) {
                return Message::toString($this->markdownRedirectResponse($resultPart));
            }
        }

        if ($resultParts !== []) {
            $contentStream = $this->contentStreamFactory->createStream(implode('', $resultParts));
            $response = $response->withBody($contentStream);
        }

        return Message::toString($response);
    }

    private function markdownRedirectResponse(MarkdownRedirectResponse $redirectResponse): ResponseInterface
    {
        $sourceResponse = $redirectResponse->getResponse();
        $headers = $this->headersWithoutBodyMetadata($sourceResponse->getHeaders());

        $headers['Location'] = [
            $this->markdownLocation($sourceResponse->getHeaderLine('Location'), $redirectResponse->getCanonicalUri()),
        ];
        $headers['Vary'] = [$this->appendVaryAccept($sourceResponse->getHeaderLine('Vary'))];

        return new Response(
            $sourceResponse->getStatusCode(),
            $headers,
            '',
            $sourceResponse->getProtocolVersion(),
            $sourceResponse->getReasonPhrase()
        );
    }

    /**
     * @param array<string, array<int, string>> $headers
     * @return array<string, array<int, string>>
     */
    private function headersWithoutBodyMetadata(array $headers): array
    {
        foreach (array_keys($headers) as $headerName) {
            if (strcasecmp((string)$headerName, 'Content-Type') === 0 || strcasecmp((string)$headerName, 'Content-Length') === 0) {
                unset($headers[$headerName]);
            }
        }

        return $headers;
    }

    private function appendVaryAccept(string $vary): string
    {
        $parts = array_filter(array_map('trim', explode(',', $vary)));
        foreach ($parts as $part) {
            if (strcasecmp($part, 'Accept') === 0) {
                return implode(', ', $parts);
            }
        }

        $parts[] = 'Accept';
        return implode(', ', $parts);
    }

    private function markdownLocation(string $location, string $canonicalUri): string
    {
        $location = trim($location);
        if ($location === '') {
            return $location;
        }

        $parts = parse_url($location);
        if (!is_array($parts) || !$this->isLocalLocation($parts, $canonicalUri)) {
            return $location;
        }

        $path = $parts['path'] ?? '';
        if ($path === '' || $path === '/') {
            $parts['path'] = '/index.md';
        } elseif (str_ends_with($path, '.html')) {
            $parts['path'] = substr($path, 0, -5) . '.md';
        } elseif (!str_ends_with($path, '.md') && !preg_match('/\.[A-Za-z0-9]{1,8}$/', basename($path))) {
            $parts['path'] = rtrim($path, '/') . '.md';
        }

        return $this->buildUrl($parts);
    }

    /**
     * @param array{
     *     scheme?: string,
     *     host?: string,
     *     port?: int,
     *     user?: string,
     *     pass?: string,
     *     path?: string,
     *     query?: string,
     *     fragment?: string
     * } $parts
     */
    private function isLocalLocation(array $parts, string $canonicalUri): bool
    {
        if (!isset($parts['scheme']) && !isset($parts['host'])) {
            return true;
        }

        $canonicalParts = parse_url($canonicalUri);
        if (!is_array($canonicalParts) || !isset($canonicalParts['host'])) {
            return false;
        }

        return strcasecmp((string)($parts['host'] ?? ''), (string)$canonicalParts['host']) === 0;
    }

    /**
     * @param array{
     *     scheme?: string,
     *     host?: string,
     *     port?: int,
     *     user?: string,
     *     pass?: string,
     *     path?: string,
     *     query?: string,
     *     fragment?: string
     * } $parts
     */
    private function buildUrl(array $parts): string
    {
        $url = '';
        if (isset($parts['scheme'])) {
            $url .= $parts['scheme'] . '://';
        }
        if (isset($parts['user'])) {
            $url .= $parts['user'];
            if (isset($parts['pass'])) {
                $url .= ':' . $parts['pass'];
            }
            $url .= '@';
        }
        if (isset($parts['host'])) {
            $url .= $parts['host'];
        }
        if (isset($parts['port'])) {
            $url .= ':' . $parts['port'];
        }

        $url .= $parts['path'] ?? '';

        if (isset($parts['query'])) {
            $url .= '?' . $parts['query'];
        }
        if (isset($parts['fragment'])) {
            $url .= '#' . $parts['fragment'];
        }

        return $url;
    }
}
