<?php

declare(strict_types=1);

namespace NEOSidekick\MarkdownForAgents\Tests\Unit\Http;

use NEOSidekick\MarkdownForAgents\Http\AcceptHeaderMatcher;
use Neos\Flow\Tests\UnitTestCase;

final class AcceptHeaderMatcherTest extends UnitTestCase
{
    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public function acceptHeaders(): array
    {
        return [
            'direct markdown' => ['text/markdown', true],
            'plain text agent fallback' => ['text/plain', true],
            'markdown before low-priority html' => ['text/markdown,text/html;q=0.1', true],
            'markdown higher q than html' => ['text/html;q=0.8,text/markdown;q=0.9', true],
            'browser-style html wins on equal q' => ['text/html,text/markdown', false],
            'html higher q than markdown' => ['text/html;q=1,text/markdown;q=0.5', false],
            'wildcard stays html' => ['*/*', false],
            'text range outranks markdown' => ['text/*;q=1,text/markdown;q=0.5', false],
            'markdown outranks text range' => ['text/*;q=0.2,text/markdown;q=0.9', true],
            'empty header stays html' => ['', false],
        ];
    }

    /**
     * @dataProvider acceptHeaders
     * @test
     */
    public function detectsWhetherMarkdownIsPreferred(string $acceptHeader, bool $expected): void
    {
        $matcher = new AcceptHeaderMatcher();

        self::assertSame($expected, $matcher->prefersMarkdown($acceptHeader));
    }
}
