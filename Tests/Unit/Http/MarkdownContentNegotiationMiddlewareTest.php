<?php

declare(strict_types=1);

namespace NEOSidekick\MarkdownForAgents\Tests\Unit\Http;

use GuzzleHttp\Psr7\ServerRequest;
use NEOSidekick\MarkdownForAgents\Http\AcceptHeaderMatcher;
use NEOSidekick\MarkdownForAgents\Http\MarkdownContentNegotiationMiddleware;
use Neos\Flow\Http\ServerRequestAttributes;
use PHPUnit\Framework\TestCase;

final class MarkdownContentNegotiationMiddlewareTest extends TestCase
{
    /**
     * @test
     */
    public function switchesFrontendNodeRequestsToMarkdownFormatAfterRouting(): void
    {
        $handler = new UnitCapturingRequestHandler();
        $request = new ServerRequest('GET', 'https://www.neosidekick.com/example/path?foo=bar', [
            'Accept' => 'text/markdown,text/html;q=0.1',
        ]);
        $request = $request->withAttribute(ServerRequestAttributes::ROUTING_RESULTS, [
            '@package' => 'Neos.Neos',
            '@controller' => 'Frontend\Node',
            '@action' => 'show',
            '@format' => 'html',
        ]);

        $middleware = new MarkdownContentNegotiationMiddleware(new AcceptHeaderMatcher());
        $middleware->process($request, $handler);

        self::assertSame('/example/path', $handler->request->getUri()->getPath());
        self::assertSame('foo=bar', $handler->request->getUri()->getQuery());
        self::assertSame('markdown', $handler->request->getAttribute(ServerRequestAttributes::ROUTING_RESULTS)['@format']);
    }

    /**
     * @test
     */
    public function switchesFrontendSlugsThatMerelyStartWithNeos(): void
    {
        $handler = new UnitCapturingRequestHandler();
        $request = new ServerRequest('GET', 'https://www.neosidekick.com/neos-consulting', [
            'Accept' => 'text/markdown',
        ]);
        $request = $request->withAttribute(ServerRequestAttributes::ROUTING_RESULTS, [
            '@package' => 'Neos.Neos',
            '@controller' => 'Frontend\Node',
            '@action' => 'show',
            '@format' => 'html',
        ]);

        $middleware = new MarkdownContentNegotiationMiddleware(new AcceptHeaderMatcher());
        $middleware->process($request, $handler);

        self::assertSame('markdown', $handler->request->getAttribute(ServerRequestAttributes::ROUTING_RESULTS)['@format']);
    }

    /**
     * @test
     */
    public function leavesNeosBackendRequestsUnchanged(): void
    {
        $handler = new UnitCapturingRequestHandler();
        $request = new ServerRequest('GET', 'https://www.neosidekick.com/neos/management', [
            'Accept' => 'text/markdown',
        ]);
        $request = $request->withAttribute(ServerRequestAttributes::ROUTING_RESULTS, [
            '@package' => 'Neos.Neos',
            '@controller' => 'Frontend\Node',
            '@action' => 'show',
            '@format' => 'html',
        ]);

        $middleware = new MarkdownContentNegotiationMiddleware(new AcceptHeaderMatcher());
        $middleware->process($request, $handler);

        self::assertSame('html', $handler->request->getAttribute(ServerRequestAttributes::ROUTING_RESULTS)['@format']);
    }

    /**
     * @test
     */
    public function keepsHtmlAcceptableForFlowControllerNegotiationWhenOnlyMarkdownWasRequested(): void
    {
        $handler = new UnitCapturingRequestHandler();
        $request = new ServerRequest('GET', 'https://www.neosidekick.com/example/path', [
            'Accept' => 'text/markdown',
        ]);
        $request = $request->withAttribute(ServerRequestAttributes::ROUTING_RESULTS, [
            '@package' => 'Neos.Neos',
            '@controller' => 'Frontend\Node',
            '@action' => 'show',
            '@format' => 'html',
        ]);

        $middleware = new MarkdownContentNegotiationMiddleware(new AcceptHeaderMatcher());
        $middleware->process($request, $handler);

        self::assertSame('markdown', $handler->request->getAttribute(ServerRequestAttributes::ROUTING_RESULTS)['@format']);
        self::assertStringContainsString('text/html', $handler->request->getHeaderLine('Accept'));
        self::assertStringContainsString('text/markdown', $handler->request->getHeaderLine('Accept'));
    }

    /**
     * @test
     */
    public function restoresHtmlAcceptabilityWhenItWasExplicitlyRejected(): void
    {
        $handler = new UnitCapturingRequestHandler();
        $request = new ServerRequest('GET', 'https://www.neosidekick.com/example/path', [
            'Accept' => 'text/markdown,text/html;q=0',
        ]);
        $request = $request->withAttribute(ServerRequestAttributes::ROUTING_RESULTS, [
            '@package' => 'Neos.Neos',
            '@controller' => 'Frontend\Node',
            '@action' => 'show',
            '@format' => 'html',
        ]);

        $middleware = new MarkdownContentNegotiationMiddleware(new AcceptHeaderMatcher());
        $middleware->process($request, $handler);

        self::assertSame('markdown', $handler->request->getAttribute(ServerRequestAttributes::ROUTING_RESULTS)['@format']);
        self::assertStringContainsString('text/markdown', $handler->request->getHeaderLine('Accept'));
        self::assertStringStartsWith('text/html', $handler->request->getHeaderLine('Accept'));
    }

    /**
     * @test
     */
    public function keepsHtmlPreferredRequestsUnchanged(): void
    {
        $handler = new UnitCapturingRequestHandler();
        $request = new ServerRequest('GET', 'https://www.neosidekick.com/example/path', [
            'Accept' => 'text/html,text/markdown',
        ]);
        $request = $request->withAttribute(ServerRequestAttributes::ROUTING_RESULTS, [
            '@package' => 'Neos.Neos',
            '@controller' => 'Frontend\Node',
            '@action' => 'show',
            '@format' => 'html',
        ]);

        $middleware = new MarkdownContentNegotiationMiddleware(new AcceptHeaderMatcher());
        $middleware->process($request, $handler);

        self::assertSame('html', $handler->request->getAttribute(ServerRequestAttributes::ROUTING_RESULTS)['@format']);
    }

    /**
     * @test
     */
    public function keepsNonFrontendRoutesUnchanged(): void
    {
        $handler = new UnitCapturingRequestHandler();
        $request = new ServerRequest('GET', 'https://www.neosidekick.com/_asset/example', [
            'Accept' => 'text/markdown,text/html;q=0.1',
        ]);
        $request = $request->withAttribute(ServerRequestAttributes::ROUTING_RESULTS, [
            '@package' => 'Example.Site',
            '@controller' => 'AssetProxy',
            '@action' => 'index',
            '@format' => 'html',
        ]);

        $middleware = new MarkdownContentNegotiationMiddleware(new AcceptHeaderMatcher());
        $middleware->process($request, $handler);

        self::assertSame('html', $handler->request->getAttribute(ServerRequestAttributes::ROUTING_RESULTS)['@format']);
    }

    /**
     * @test
     */
    public function leavesRequestsWithoutRoutingResultsUntouched(): void
    {
        $handler = new UnitCapturingRequestHandler();
        $request = new ServerRequest('GET', 'https://www.neosidekick.com/example/path', [
            'Accept' => 'text/html,text/markdown',
        ]);

        $middleware = new MarkdownContentNegotiationMiddleware(new AcceptHeaderMatcher());
        $middleware->process($request, $handler);

        self::assertSame('/example/path', $handler->request->getUri()->getPath());
    }

    /**
     * @test
     */
    public function keepsHtmlAcceptableForMarkdownRouteRequestsSoTheControllerDoesNotReturn406(): void
    {
        $handler = new UnitCapturingRequestHandler();
        $request = new ServerRequest('GET', 'https://www.neosidekick.com/company/about-us.md', [
            'Accept' => 'text/markdown',
        ]);
        $request = $request->withAttribute(ServerRequestAttributes::ROUTING_RESULTS, [
            '@package' => 'Neos.Neos',
            '@controller' => 'Frontend\Node',
            '@action' => 'show',
            '@format' => 'markdown',
        ]);

        $middleware = new MarkdownContentNegotiationMiddleware(new AcceptHeaderMatcher());
        $middleware->process($request, $handler);

        self::assertSame('markdown', $handler->request->getAttribute(ServerRequestAttributes::ROUTING_RESULTS)['@format']);
        self::assertStringContainsString('text/html', $handler->request->getHeaderLine('Accept'));
        self::assertStringContainsString('text/markdown', $handler->request->getHeaderLine('Accept'));
    }

    /**
     * @test
     */
    public function leavesMarkdownRouteRequestsThatAlreadyAcceptHtmlUntouched(): void
    {
        $handler = new UnitCapturingRequestHandler();
        $request = new ServerRequest('GET', 'https://www.neosidekick.com/company/about-us.md', [
            'Accept' => 'text/html,application/xhtml+xml,*/*;q=0.8',
        ]);
        $request = $request->withAttribute(ServerRequestAttributes::ROUTING_RESULTS, [
            '@package' => 'Neos.Neos',
            '@controller' => 'Frontend\Node',
            '@action' => 'show',
            '@format' => 'markdown',
        ]);

        $middleware = new MarkdownContentNegotiationMiddleware(new AcceptHeaderMatcher());
        $middleware->process($request, $handler);

        self::assertSame('markdown', $handler->request->getAttribute(ServerRequestAttributes::ROUTING_RESULTS)['@format']);
        self::assertSame('text/html,application/xhtml+xml,*/*;q=0.8', $handler->request->getHeaderLine('Accept'));
    }
}
