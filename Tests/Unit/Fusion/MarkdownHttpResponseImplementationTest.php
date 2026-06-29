<?php

declare(strict_types=1);

namespace NEOSidekick\MarkdownForAgents\Tests\Unit\Fusion;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\Response;
use NEOSidekick\MarkdownForAgents\Fusion\MarkdownHttpResponseImplementation;
use NEOSidekick\MarkdownForAgents\Fusion\MarkdownRedirectResponse;
use Neos\Flow\Tests\UnitTestCase;

final class MarkdownHttpResponseImplementationTest extends UnitTestCase
{
    /**
     * @test
     */
    public function keepsTheHttpMessageFusionApiForRegularMarkdownResponses(): void
    {
        $documentResponse = $this->documentResponse([
            'httpResponseHead' => new Response(200, [
                'Content-Type' => 'text/markdown; charset=utf-8',
                'Vary' => 'Accept',
                'Link' => '<https://example.test/page>; rel="canonical"',
            ]),
            'body' => '# Title',
        ]);

        $response = Message::parseResponse($documentResponse->evaluate());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/markdown; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('Accept', $response->getHeaderLine('Vary'));
        self::assertSame('<https://example.test/page>; rel="canonical"', $response->getHeaderLine('Link'));
        self::assertSame('# Title', (string)$response->getBody());
    }

    /**
     * @test
     */
    public function keepsRedirectStatusAndPointsLocalRedirectsToMarkdownVariant(): void
    {
        $documentResponse = $this->documentResponse([
            'httpResponseHead' => new Response(200, [
                'Content-Type' => 'text/markdown; charset=utf-8',
                'Vary' => 'Accept',
            ]),
            'body' => new MarkdownRedirectResponse(
                new Response(301, ['Location' => '/news']),
                'https://example.test/news/2026'
            ),
        ]);

        $response = Message::parseResponse($documentResponse->evaluate());

        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/news.md', $response->getHeaderLine('Location'));
        self::assertSame('Accept', $response->getHeaderLine('Vary'));
        self::assertSame('', $response->getHeaderLine('Content-Type'));
        self::assertSame('', (string)$response->getBody());
    }

    /**
     * @test
     */
    public function rewritesLocalRootRedirectsToIndexMarkdown(): void
    {
        $documentResponse = $this->documentResponse([
            'httpResponseHead' => new Response(200),
            'body' => new MarkdownRedirectResponse(
                new Response(303, ['Location' => 'https://example.test/']),
                'https://example.test/shortcut'
            ),
        ]);

        $response = Message::parseResponse($documentResponse->evaluate());

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('https://example.test/index.md', $response->getHeaderLine('Location'));
    }

    /**
     * @test
     */
    public function keepsExternalRedirectLocationsUnchanged(): void
    {
        $documentResponse = $this->documentResponse([
            'httpResponseHead' => new Response(200),
            'body' => new MarkdownRedirectResponse(
                new Response(302, ['Location' => 'https://external.test/']),
                'https://example.test/shortcut'
            ),
        ]);

        $response = Message::parseResponse($documentResponse->evaluate());

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('https://external.test/', $response->getHeaderLine('Location'));
    }

    /**
     * @param array<string, mixed> $values
     */
    private function documentResponse(array $values): MarkdownHttpResponseImplementation
    {
        $runtime = new MarkdownRendererTestRuntime($values, []);
        $documentResponse = new MarkdownHttpResponseImplementation($runtime, '/renderer', 'NEOSidekick.MarkdownForAgents:DocumentResponse');
        $documentResponse['httpResponseHead'] = true;
        $documentResponse['body'] = true;
        $this->inject($documentResponse, 'contentStreamFactory', new HttpFactory());

        return $documentResponse;
    }
}
