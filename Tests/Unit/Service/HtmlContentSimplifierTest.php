<?php

declare(strict_types=1);

namespace NEOSidekick\MarkdownForAgents\Tests\Unit\Service;

use NEOSidekick\MarkdownForAgents\Service\HtmlContentSimplifier;
use Neos\Flow\Tests\UnitTestCase;

final class HtmlContentSimplifierTest extends UnitTestCase
{
    private HtmlContentSimplifier $simplifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->simplifier = new HtmlContentSimplifier();
    }

    /**
     * @test
     */
    public function spacesOutLineBreaksInsideHeadings(): void
    {
        self::assertSame(
            "<h1>Foo Bar</h1><h2>Foo Bar</h2><h3>Foo Bar</h3>",
            $this->simplifier->simplify("<h1>Foo<br>Bar</h1><h2>Foo<br/>Bar</h2><h3>Foo<br />Bar</h3>")
        );
    }

    /**
     * @test
     */
    public function spacesOutLineBreaksInsideLinks(): void
    {
        self::assertSame(
            '<p><a href="/x">Foo Bar</a></p>',
            $this->simplifier->simplify('<p><a href="/x">Foo<br>Bar</a></p>')
        );
    }

    /**
     * @test
     */
    public function keepsLineBreaksInFlowingContent(): void
    {
        self::assertSame(
            "<p>Foo<br>Bar</p>",
            $this->simplifier->simplify("<p>Foo<br>Bar</p>")
        );
    }
}
