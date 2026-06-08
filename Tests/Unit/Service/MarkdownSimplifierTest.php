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
}
