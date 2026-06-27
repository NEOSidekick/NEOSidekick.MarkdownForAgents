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
        self::assertSame([], $options->tagSeparatorAfter);
        self::assertSame([], $options->imageSourcePreference);
        self::assertNull($options->srcsetMaxCandidateWidth);
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
            'tagSeparatorAfter' => ['dt' => ': ', 'dd' => ' '],
            'imageSourcePreference' => ['data-markdown-src' => true, 'srcset' => false],
            'srcsetMaxCandidateWidth' => 1200,
        ]);

        self::assertSame('https://example.test/page', $options->canonicalUri);
        self::assertSame('Formular auf dieser Seite', $options->formNoticeLabel);
        self::assertSame('Eingebetteter Inhalt', $options->iframeFallbackLabel);
        self::assertFalse($options->removeNavigation);
        self::assertTrue($options->removeLinks);
        self::assertFalse($options->keepEmptyAltImages);
        self::assertSame(['.foo' => true, 'footer' => false], $options->removeSelectors);
        self::assertSame(['dt' => ': ', 'dd' => ' '], $options->tagSeparatorAfter);
        self::assertSame(['data-markdown-src' => true, 'srcset' => false], $options->imageSourcePreference);
        self::assertSame(1200, $options->srcsetMaxCandidateWidth);
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

    /**
     * @test
     */
    public function coercesImageSourcePreferenceValuesToBooleansAndKeepsFalse(): void
    {
        $options = ConversionOptions::fromArray(['imageSourcePreference' => ['srcset' => false, 'src' => 1]]);

        self::assertSame(['srcset' => false, 'src' => true], $options->imageSourcePreference);
    }

    /**
     * @test
     */
    public function rejectsANegativeSrcsetMaxCandidateWidth(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1769270008);

        ConversionOptions::fromArray(['srcsetMaxCandidateWidth' => -1]);
    }

    /**
     * @test
     */
    public function rejectsANonIntegerSrcsetMaxCandidateWidth(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1769270008);

        ConversionOptions::fromArray(['srcsetMaxCandidateWidth' => '1600']);
    }
}
