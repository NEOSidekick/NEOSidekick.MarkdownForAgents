<?php

declare(strict_types=1);

namespace NEOSidekick\MarkdownForAgents\Tests\Unit\Service;

use NEOSidekick\MarkdownForAgents\Service\MarkdownSimplifier;
use Neos\Flow\Tests\UnitTestCase;

final class MarkdownSimplifierTest extends UnitTestCase
{
    private MarkdownSimplifier $simplifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->simplifier = new MarkdownSimplifier();
    }

    /**
     * @test
     */
    public function removesAnEmptyBulletBetweenRealItems(): void
    {
        self::assertSame(
            "- First point\n- Second point",
            $this->simplifier->simplify("- First point\n- \n- Second point\n")
        );
    }

    /**
     * @test
     */
    public function removesAnEmptyBlockquoteLine(): void
    {
        self::assertSame(
            '> A real quote',
            $this->simplifier->simplify("> A real quote\n> \n")
        );
    }

    /**
     * @test
     */
    public function keepsRealBulletItems(): void
    {
        self::assertSame(
            "- Keep me\n- And me",
            $this->simplifier->simplify("- Keep me\n- And me")
        );
    }

    /**
     * @test
     */
    public function keepsThematicBreaks(): void
    {
        // A horizontal rule starts with '-' but must not be mistaken for an empty item.
        self::assertSame(
            "Intro line\n\n---\n\nNext section",
            $this->simplifier->simplify("Intro line\n\n---\n\nNext section")
        );
    }

    /**
     * @test
     */
    public function doesNotGlueAFollowingParagraphToAnEmptyTrailingListItem(): void
    {
        // The empty item is removed, but the blank line that separates the list
        // from the next paragraph must survive — otherwise the paragraph becomes
        // a lazy continuation of the list item.
        self::assertSame(
            "- Item\n\nNext paragraph",
            $this->simplifier->simplify("- Item\n- \n\nNext paragraph")
        );
    }

    /**
     * @test
     */
    public function collapsesExcessiveBlankLines(): void
    {
        self::assertSame("A\n\nB", $this->simplifier->simplify("A\n\n\n\nB"));
    }

    /**
     * @test
     */
    public function normalizesNonBreakingSpaces(): void
    {
        self::assertSame('a b', $this->simplifier->simplify("a\xc2\xa0b"));
    }

    /**
     * @test
     */
    public function deGluesAHeadingFusedToPrecedingText(): void
    {
        self::assertSame(
            "Some intro text\n\n## Heading",
            $this->simplifier->simplify('Some intro text## Heading')
        );
    }

    /**
     * @test
     */
    public function deGluesHeadingsAfterInlineFormattingAndImages(): void
    {
        self::assertSame(
            "**better**\n\n## Werte",
            $this->simplifier->simplify('**better**## Werte')
        );
        self::assertSame(
            "![alt](/x.png)\n\n#### Name",
            $this->simplifier->simplify('![alt](/x.png)#### Name')
        );
    }

    /**
     * @test
     */
    public function keepsHeadingsThatAlreadyStartTheirLine(): void
    {
        self::assertSame(
            "# Title\n\nBody text",
            $this->simplifier->simplify("# Title\n\nBody text")
        );
    }

    /**
     * @test
     */
    public function doesNotSplitInlineHashTokensLikeCSharp(): void
    {
        self::assertSame(
            'We build APIs in C# and F# daily',
            $this->simplifier->simplify('We build APIs in C# and F# daily')
        );
    }
}
