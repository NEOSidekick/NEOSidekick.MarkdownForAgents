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
    public function addsSpaceAfterLineBreakIfNeeded(): void
    {
        self::assertSame(
            "<p>Foo<br> Bar</p><p>Foo<br> Bar</p><p>Foo<br> Bar</p><p>Foo<br> Bar</p><p>Foo<br> Bar</p><p>Foo<br> Bar</p>",
            $this->simplifier->simplify("<p>Foo<br>Bar</p><p>Foo<br/>Bar</p><p>Foo<br />Bar</p><p>Foo<br> Bar</p><p>Foo<br/> Bar</p><p>Foo<br /> Bar</p>")
        );
    }
}
