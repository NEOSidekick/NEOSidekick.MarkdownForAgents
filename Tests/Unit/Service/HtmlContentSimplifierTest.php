<?php

declare(strict_types=1);

namespace NEOSidekick\MarkdownForAgents\Tests\Unit\Service;

use NEOSidekick\MarkdownForAgents\Dto\ConversionOptions;
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

    /**
     * @test
     */
    public function prefersAnExplicitMarkdownImageSourceOverSrcsetAndSrc(): void
    {
        $html = '<img alt="Hero" src="/thumbnail.jpg" srcset="/medium.jpg 800w, /large.jpg 1600w" data-markdown-src="/original.jpg">';

        $simplified = $this->simplifier->simplify($html);

        self::assertStringContainsString('src="/original.jpg"', $simplified);
    }

    /**
     * @test
     */
    public function picksTheLargestSrcsetCandidateBelowTheConfiguredWidth(): void
    {
        $html = '<img alt="Hero" src="/thumbnail.jpg" srcset="/small.jpg 400w, /medium.jpg 1200w, /large.jpg 2400w">';

        $simplified = $this->simplifier->simplify($html);

        self::assertStringContainsString('src="/medium.jpg"', $simplified);
    }

    /**
     * @test
     */
    public function keepsCommasInsideSrcsetCandidateUrls(): void
    {
        $html = '<img alt="Hero" src="/thumbnail.jpg" srcset="https://cdn.example.test/w_300,c_fill/photo.jpg 300w, https://cdn.example.test/w_1600,c_fill/photo.jpg 1600w">';

        $simplified = $this->simplifier->simplify($html);

        self::assertStringContainsString('src="https://cdn.example.test/w_1600,c_fill/photo.jpg"', $simplified);
    }

    /**
     * @test
     */
    public function picksTheSmallestLargerSrcsetCandidateWhenAllCandidatesExceedTheConfiguredWidth(): void
    {
        $html = '<img alt="Hero" src="/thumbnail.jpg" srcset="/large.jpg 2000w, /larger.jpg 2600w">';

        $simplified = $this->simplifier->simplify($html);

        self::assertStringContainsString('src="/large.jpg"', $simplified);
    }

    /**
     * @test
     */
    public function picksThePreferredLazyLoadedDataSrcsetCandidate(): void
    {
        $html = '<img alt="Hero" src="/placeholder-100x56.jpg" data-src="/lazy.jpg" data-srcset="/small.jpg 400w, /large.jpg 1600w, /larger.jpg 2400w">';

        $simplified = $this->simplifier->simplify($html);

        self::assertStringContainsString('src="/large.jpg"', $simplified);
    }

    /**
     * @test
     */
    public function picksPictureSourceSrcsetBeforeSourceElementsAreRemoved(): void
    {
        $html = '<picture><source srcset="/small.jpg 400w, /large.jpg 1600w"><img alt="Hero" src="/thumbnail.jpg"></picture>';
        $this->inject($this->simplifier, 'removeSelectors', ['source' => true]);

        $simplified = $this->simplifier->simplify($html);

        self::assertStringContainsString('src="/large.jpg"', $simplified);
        self::assertStringNotContainsString('<source', $simplified);
    }

    /**
     * @test
     */
    public function parsesCustomAttributesEndingInSrcsetAsSrcsetCandidates(): void
    {
        $html = '<img alt="Hero" src="/placeholder.jpg" data-markdown-srcset="/small.jpg 400w, /large.jpg 1600w">';
        $this->inject($this->simplifier, 'imageSourcePreference', ['data-markdown-srcset' => true, 'src' => true]);

        $simplified = $this->simplifier->simplify($html);

        self::assertStringContainsString('src="/large.jpg"', $simplified);
    }

    /**
     * @test
     */
    public function malformedSrcsetLikeAttributesFallBackToTheFirstUsefulUrl(): void
    {
        $html = '<img alt="Hero" src="/placeholder.jpg" data-markdown-srcset="/first.jpg, /second.jpg">';
        $this->inject($this->simplifier, 'imageSourcePreference', ['data-markdown-srcset' => true, 'src' => true]);

        $simplified = $this->simplifier->simplify($html);

        self::assertStringContainsString('src="/first.jpg"', $simplified);
    }

    /**
     * @test
     */
    public function malformedSrcsetLikeAttributesIgnoreFragmentsWithoutUrlShape(): void
    {
        $html = '<img alt="Hero" src="/placeholder.jpg" data-markdown-srcset="w_300, /useful.jpg">';
        $this->inject($this->simplifier, 'imageSourcePreference', ['data-markdown-srcset' => true, 'src' => true]);

        $simplified = $this->simplifier->simplify($html);

        self::assertStringContainsString('src="/useful.jpg"', $simplified);
    }

    /**
     * @test
     */
    public function skipsDataUriSrcsetCandidatesAndKeepsParsingLaterCandidates(): void
    {
        $html = '<img alt="Hero" src="/placeholder.jpg" srcset="data:image/png;base64,iVBORw0KGgo= 1x, /fallback.jpg 2x">';

        $simplified = $this->simplifier->simplify($html);

        self::assertStringContainsString('src="/fallback.jpg"', $simplified);
    }

    /**
     * @test
     */
    public function fallsBackToLazyLoadedDataSrcBeforePlaceholderSrc(): void
    {
        $html = '<img alt="Hero" src="/placeholder-100x56.jpg" data-src="/lazy.jpg">';

        $simplified = $this->simplifier->simplify($html);

        self::assertStringContainsString('src="/lazy.jpg"', $simplified);
    }

    /**
     * @test
     */
    public function picksTheLargestSrcsetCandidateWhenTheConfiguredWidthIsZero(): void
    {
        $html = '<img alt="Hero" src="/thumbnail.jpg" srcset="/small.jpg 400w, /medium.jpg 1200w, /large.jpg 2400w">';

        $simplified = $this->simplifier->simplify($html, ConversionOptions::fromArray([
            'srcsetMaxCandidateWidth' => 0,
        ]));

        self::assertStringContainsString('src="/large.jpg"', $simplified);
    }

    /**
     * @test
     */
    public function sourcePreferenceCanDisableSrcsetAndFallBackToSrc(): void
    {
        $html = '<img alt="Hero" src="/thumbnail.jpg" srcset="/large.jpg 1600w">';

        $simplified = $this->simplifier->simplify($html, ConversionOptions::fromArray([
            'imageSourcePreference' => ['srcset' => false],
        ]));

        self::assertStringContainsString('src="/thumbnail.jpg"', $simplified);
    }

    /**
     * @test
     */
    public function sourcePreferenceCanAddCustomImageSourceAttributes(): void
    {
        $html = '<img alt="Hero" src="/thumbnail.jpg" data-original-src="/original.jpg">';
        $this->inject($this->simplifier, 'imageSourcePreference', ['data-original-src' => true, 'src' => true]);

        $simplified = $this->simplifier->simplify($html);

        self::assertStringContainsString('src="/original.jpg"', $simplified);
    }
}
