<?php

declare(strict_types=1);

namespace NEOSidekick\MarkdownForAgents\Tests\Unit\EelHelper;

use NEOSidekick\MarkdownForAgents\EelHelper\MarkdownHelper;
use Neos\Flow\Tests\UnitTestCase;

final class MarkdownHelperTest extends UnitTestCase
{
    /**
     * @test
     */
    public function convertsHtmlToMarkdown(): void
    {
        $helper = new MarkdownHelper();

        self::assertStringContainsString('Heading', $helper->htmlToMarkdown('<main><h1>Heading</h1></main>'));
    }

    /**
     * @test
     */
    public function allowsEelAccessToMarkdownMethods(): void
    {
        $helper = new MarkdownHelper();

        self::assertTrue($helper->allowsCallOfMethod('htmlToMarkdown'));
    }
}
