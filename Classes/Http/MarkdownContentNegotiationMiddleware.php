<?php

declare(strict_types=1);

namespace NEOSidekick\MarkdownForAgents\Http;

use Neos\Flow\Http\ServerRequestAttributes;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MarkdownContentNegotiationMiddleware implements MiddlewareInterface
{
    private AcceptHeaderMatcher $acceptHeaderMatcher;

    public function __construct(?AcceptHeaderMatcher $acceptHeaderMatcher = null)
    {
        $this->acceptHeaderMatcher = $acceptHeaderMatcher ?? new AcceptHeaderMatcher();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
    {
        $routingResults = $this->frontendNodeShowRoutingResults($request);
        if ($routingResults === null) {
            return $next->handle($request);
        }

        $format = $routingResults['@format'] ?? 'html';

        if ($format === 'html') {
            // Accept-header negotiation: client prefers Markdown, so switch the format.
            if (!$this->acceptHeaderMatcher->prefersMarkdown($request->getHeaderLine('Accept'))) {
                return $this->withVaryOnAccept($next->handle($request));
            }

            $routingResults['@format'] = 'markdown';

            return $this->withVaryOnAccept($next->handle(
                $request
                    ->withAttribute(ServerRequestAttributes::ROUTING_RESULTS, $routingResults)
                    ->withHeader('Accept', $this->withHtmlFallbackAcceptHeader($request->getHeaderLine('Accept')))
            ));
        }

        if ($format === 'markdown') {
            // Avoid a 406 when an agent requests a ".md" route with only
            // "Accept: text/markdown".
            return $next->handle(
                $request->withHeader('Accept', $this->withHtmlFallbackAcceptHeader($request->getHeaderLine('Accept')))
            );
        }

        return $next->handle($request);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function frontendNodeShowRoutingResults(ServerRequestInterface $request): ?array
    {
        if (!in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return null;
        }

        $path = $request->getUri()->getPath();

        if (
            $path === '/neos' || str_starts_with($path, '/neos/')
            || $path === '/_Resources' || str_starts_with($path, '/_Resources/')
        ) {
            return null;
        }

        $routingResults = $request->getAttribute(ServerRequestAttributes::ROUTING_RESULTS);
        if (!is_array($routingResults) || $routingResults === []) {
            return null;
        }

        if (
            ($routingResults['@package'] ?? null) !== 'Neos.Neos'
            || ($routingResults['@controller'] ?? null) !== 'Frontend\Node'
            || ($routingResults['@action'] ?? null) !== 'show'
        ) {
            return null;
        }

        return $routingResults;
    }

    /**
     * The same URL serves HTML or Markdown depending on Accept, so shared caches
     * must not hand a cached HTML response to a Markdown client (or vice versa).
     */
    private function withVaryOnAccept(ResponseInterface $response): ResponseInterface
    {
        $varyFields = array_values(array_filter(array_map('trim', explode(',', $response->getHeaderLine('Vary')))));
        foreach ($varyFields as $varyField) {
            if ($varyField === '*' || strcasecmp($varyField, 'Accept') === 0) {
                return $response;
            }
        }

        $varyFields[] = 'Accept';
        return $response->withHeader('Vary', implode(', ', $varyFields));
    }

    private function withHtmlFallbackAcceptHeader(string $acceptHeader): string
    {
        if ($this->acceptHeaderMatcher->acceptsHtml($acceptHeader)) {
            return $acceptHeader;
        }

        $acceptHeader = trim($acceptHeader);
        return $acceptHeader === '' ? 'text/html' : 'text/html,' . $acceptHeader;
    }
}
