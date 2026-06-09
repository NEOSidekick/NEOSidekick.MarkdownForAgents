<?php

declare(strict_types=1);

namespace NEOSidekick\MarkdownForAgents\Tests\Unit\Dto;

use NEOSidekick\MarkdownForAgents\Dto\ConversionOptions;
use Neos\Flow\Tests\UnitTestCase;

final class ConversionOptionsTest extends UnitTestCase
{
    /**
     * @test
     */
    public function emptyArrayYieldsTheDocumentedDefaults(): void
    {
        $options = ConversionOptions::fromArray([]);

        self::assertSame('', $options->canonicalUri);
        self::assertSame('', $options->formNoticeLabel);
        self::assertSame('', $options->iframeFallbackLabel);
        self::assertNull($options->removeNavigation);
        self::assertNull($options->removeLinks);
        self::assertNull($options->keepEmptyAltImages);
        self::assertSame([], $options->removeSelectors);
    }

    /**
     * @test
     */
    public function mapsAllKnownOptions(): void
    {
        $options = ConversionOptions::fromArray([
            'canonicalUri' => 'https://example.test/page',
            'formNoticeLabel' => 'Formular auf dieser Seite',
            'iframeFallbackLabel' => 'Eingebetteter Inhalt',
            'removeNavigation' => false,
            'removeLinks' => true,
            'keepEmptyAltImages' => false,
            'removeSelectors' => ['.foo' => true, 'footer' => false],
        ]);

        self::assertSame('https://example.test/page', $options->canonicalUri);
        self::assertSame('Formular auf dieser Seite', $options->formNoticeLabel);
        self::assertSame('Eingebetteter Inhalt', $options->iframeFallbackLabel);
        self::assertFalse($options->removeNavigation);
        self::assertTrue($options->removeLinks);
        self::assertFalse($options->keepEmptyAltImages);
        self::assertSame(['.foo' => true, 'footer' => false], $options->removeSelectors);
    }

    /**
     * @test
     */
    public function rejectsUnknownOptionsToCatchTypos(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1769270001);

        ConversionOptions::fromArray(['removeLink' => true]);
    }

    /**
     * @test
     */
    public function rejectsAMistypedStringOption(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1769270002);

        ConversionOptions::fromArray(['canonicalUri' => ['not', 'a', 'string']]);
    }

    /**
     * @test
     */
    public function rejectsAMistypedBooleanOption(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1769270003);

        ConversionOptions::fromArray(['removeLinks' => 'yes']);
    }

    /**
     * @test
     */
    public function rejectsANonArrayRemoveSelectors(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1769270004);

        ConversionOptions::fromArray(['removeSelectors' => 'footer']);
    }

    /**
     * @test
     */
    public function treatsWhitespaceOnlyStringsAsEmpty(): void
    {
        $options = ConversionOptions::fromArray(['canonicalUri' => '   ']);

        self::assertSame('', $options->canonicalUri);
    }

    /**
     * @test
     */
    public function keepsAnExplicitNullBooleanUnset(): void
    {
        $options = ConversionOptions::fromArray(['removeLinks' => null]);

        self::assertNull($options->removeLinks);
    }

    /**
     * @test
     */
    public function coercesSelectorMapValuesToBooleansAndKeepsFalse(): void
    {
        $options = ConversionOptions::fromArray(['removeSelectors' => ['.keep' => false, '.drop' => 1]]);

        self::assertSame(['.keep' => false, '.drop' => true], $options->removeSelectors);
    }
}
