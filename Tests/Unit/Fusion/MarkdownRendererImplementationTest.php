<?php

declare(strict_types=1);

namespace NEOSidekick\MarkdownForAgents\Tests\Unit\Fusion;

use NEOSidekick\MarkdownForAgents\Fusion\MarkdownRedirectResponse;
use NEOSidekick\MarkdownForAgents\Fusion\MarkdownRendererImplementation;
use NEOSidekick\MarkdownForAgents\Service\MarkdownConverter;
use Neos\Flow\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

final class MarkdownRendererImplementationTest extends UnitTestCase
{
    /**
     * @test
     */
    public function returnsMarkdownErrorFallbackWhenHtmlFallbackRenderingThrows(): void
    {
        $runtime = new MarkdownRendererTestRuntime(
            [
                'type' => 'Example.Site:Document.BlogPost',
                'suffix' => '.Markdown',
                'fallbackToHtml' => true,
                'title' => 'Broken Blog Post',
                'canonicalUri' => 'https://example.test/blog/broken',
            ],
            [
                '/type<Example.Site:Document.BlogPost.Markdown>' => false,
            ],
            [],
            [
                '/renderer/element<Example.Site:Document.BlogPost>' => new \RuntimeException('Fusion exploded'),
            ]
        );
        $renderer = new MarkdownRendererImplementation($runtime, '/renderer', 'NEOSidekick.MarkdownForAgents:MarkdownRenderer');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(
            self::stringContains('Markdown rendering failed'),
            self::arrayHasKey('exception')
        );
        $this->inject($renderer, 'markdownConverter', new MarkdownConverter());
        $this->inject($renderer, 'logger', $logger);

        $markdown = $renderer->evaluate();

        self::assertStringContainsString('# Broken Blog Post', $markdown);
        self::assertStringContainsString('could not be rendered as Markdown', $markdown);
        self::assertStringContainsString('https://example.test/blog/broken', $markdown);
        self::assertStringNotContainsString('Fusion exploded', $markdown);
    }

    /**
     * @test
     */
    public function fallsBackToConvertedHtmlWhenExplicitMarkdownPrototypeThrows(): void
    {
        $runtime = new MarkdownRendererTestRuntime(
            [
                'type' => 'Example.Site:Document.BlogPost',
                'suffix' => '.Markdown',
                'fallbackToHtml' => true,
                'title' => 'Blog Post',
                'canonicalUri' => 'https://example.test/blog/post',
            ],
            [
                '/type<Example.Site:Document.BlogPost.Markdown>' => true,
            ],
            [
                '/renderer/element<Example.Site:Document.BlogPost>' => '<main><h1>HTML Fallback</h1><p>Useful content.</p></main>',
            ],
            [
                '/renderer/element<Example.Site:Document.BlogPost.Markdown>' => new \RuntimeException('Markdown prototype exploded'),
            ]
        );
        $renderer = new MarkdownRendererImplementation($runtime, '/renderer', 'NEOSidekick.MarkdownForAgents:MarkdownRenderer');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(
            self::stringContains('Explicit Markdown Fusion prototype failed'),
            self::arrayHasKey('exception')
        );
        $this->inject($renderer, 'markdownConverter', new MarkdownConverter());
        $this->inject($renderer, 'logger', $logger);

        $markdown = $renderer->evaluate();

        self::assertStringContainsString('HTML Fallback', $markdown);
        self::assertStringContainsString('Useful content.', $markdown);
        self::assertStringNotContainsString('Markdown prototype exploded', $markdown);
    }

    /**
     * @test
     */
    public function returnsMarkdownErrorFallbackWhenFusionReturnsHtmlExceptionOutput(): void
    {
        $runtime = new MarkdownRendererTestRuntime(
            [
                'type' => 'Example.Site:Document.BlogPost',
                'suffix' => '.Markdown',
                'fallbackToHtml' => true,
                'title' => 'Broken Error Output',
                'canonicalUri' => 'https://example.test/blog/error-output',
            ],
            [
                '/type<Example.Site:Document.BlogPost.Markdown>' => false,
            ],
            [
                '/renderer/element<Example.Site:Document.BlogPost>' => '<main><h1>An exception was thrown while Neos tried to render your page</h1></main>',
            ]
        );
        $renderer = new MarkdownRendererImplementation($runtime, '/renderer', 'NEOSidekick.MarkdownForAgents:MarkdownRenderer');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(
            self::stringContains('Markdown rendering failed'),
            self::arrayHasKey('exception')
        );
        $this->inject($renderer, 'markdownConverter', new MarkdownConverter());
        $this->inject($renderer, 'logger', $logger);

        $markdown = $renderer->evaluate();

        self::assertStringContainsString('# Broken Error Output', $markdown);
        self::assertStringContainsString('could not be rendered as Markdown', $markdown);
        self::assertStringNotContainsString('An exception was thrown while Neos tried to render your page', $markdown);
    }

    /**
     * @test
     */
    public function htmlFallbackRendersTheDocumentBodyWithoutTheHttpMessageHead(): void
    {
        $runtime = new MarkdownRendererTestRuntime(
            [
                'type' => 'Example.Site:Document.Page',
                'suffix' => '.Markdown',
                'fallbackToHtml' => true,
            ],
            [
                '/type<Example.Site:Document.Page.Markdown>' => false,
            ],
            [
                // The renderer renders the whole document element (a Http.Message),
                // so the document's @context is applied; the serialized head is then
                // stripped before conversion.
                '/renderer/element<Example.Site:Document.Page>' => "HTTP/1.1 200 OK\r\nContent-Type: text/html\r\n\r\n<main><h1>Real Body</h1></main>",
            ]
        );
        $renderer = new MarkdownRendererImplementation($runtime, '/renderer', 'NEOSidekick.MarkdownForAgents:MarkdownRenderer');
        $this->inject($renderer, 'markdownConverter', new MarkdownConverter());

        $markdown = $renderer->evaluate();

        self::assertStringContainsString('Real Body', $markdown);
        self::assertStringNotContainsString('HTTP/1.1', $markdown);
        self::assertStringNotContainsString('Content-Type', $markdown);
    }

    /**
     * @test
     */
    public function htmlFallbackReturnsRedirectMarkerForRedirectHttpMessages(): void
    {
        $runtime = new MarkdownRendererTestRuntime(
            [
                'type' => 'Example.Site:Document.Archive',
                'suffix' => '.Markdown',
                'fallbackToHtml' => true,
                'canonicalUri' => 'https://example.test/news/2026',
            ],
            [
                '/type<Example.Site:Document.Archive.Markdown>' => false,
            ],
            [
                '/renderer/element<Example.Site:Document.Archive>' => "HTTP/1.1 301 Moved Permanently\r\nLocation: /news\r\n\r\n",
            ]
        );
        $renderer = new MarkdownRendererImplementation($runtime, '/renderer', 'NEOSidekick.MarkdownForAgents:MarkdownRenderer');
        $this->inject($renderer, 'markdownConverter', new MarkdownConverter());

        $result = $renderer->evaluate();

        self::assertInstanceOf(MarkdownRedirectResponse::class, $result);
        self::assertSame(301, $result->getResponse()->getStatusCode());
        self::assertSame('/news', $result->getResponse()->getHeaderLine('Location'));
        self::assertSame('https://example.test/news/2026', $result->getCanonicalUri());
    }

    /**
     * @test
     */
    public function rendersMarkdownEvenWhenContentCacheStateCannotBeRead(): void
    {
        $runtime = new MarkdownRendererTestRuntime(
            [
                'type' => 'Example.Site:Document.BlogPost',
                'suffix' => '.Markdown',
                'fallbackToHtml' => true,
            ],
            [
                '/type<Example.Site:Document.BlogPost.Markdown>' => true,
            ],
            [
                '/renderer/element<Example.Site:Document.BlogPost.Markdown>' => '# Dedicated Markdown',
            ],
            [],
            true
        );
        $renderer = new MarkdownRendererImplementation($runtime, '/renderer', 'NEOSidekick.MarkdownForAgents:MarkdownRenderer');
        $this->inject($renderer, 'markdownConverter', new MarkdownConverter());

        self::assertSame('# Dedicated Markdown', $renderer->evaluate());
    }
}
