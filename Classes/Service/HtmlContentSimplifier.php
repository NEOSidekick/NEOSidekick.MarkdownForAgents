<?php

declare(strict_types=1);

namespace NEOSidekick\MarkdownForAgents\Service;

use DOMNode;
use NEOSidekick\MarkdownForAgents\Dto\ConversionOptions;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\Translator;
use Symfony\Component\DomCrawler\Crawler;

final class HtmlContentSimplifier
{
    /**
     * @Flow\InjectConfiguration(path="htmlContentSimplifier.removeSelectors", package="NEOSidekick.MarkdownForAgents")
     * @var array<string, bool>
     */
    protected array $removeSelectors = [];

    /**
     * @Flow\InjectConfiguration(path="htmlContentSimplifier.navigationSelectors", package="NEOSidekick.MarkdownForAgents")
     * @var array<string, bool>
     */
    protected array $navigationSelectors = [];

    /**
     * @Flow\InjectConfiguration(path="htmlContentSimplifier.tagSeparatorAfter", package="NEOSidekick.MarkdownForAgents")
     * @var array<string, string>
     */
    protected array $tagSeparatorAfter = [];

    /**
     * @Flow\InjectConfiguration(path="images.sourcePreference", package="NEOSidekick.MarkdownForAgents")
     * @var array<string, bool>
     */
    protected array $imageSourcePreference = [
        'data-markdown-src' => true,
        'srcset' => true,
        'data-srcset' => true,
        'data-src' => true,
        'src' => true,
    ];

    /**
     * @Flow\InjectConfiguration(path="images.srcsetMaxCandidateWidth", package="NEOSidekick.MarkdownForAgents")
     * @var int
     */
    protected int $srcsetMaxCandidateWidth = 1600;

    /**
     * @Flow\InjectConfiguration(path="htmlContentSimplifier.keepEmptyAltImages", package="NEOSidekick.MarkdownForAgents")
     * @var bool
     */
    protected bool $keepEmptyAltImages = true;

    /**
     * @Flow\InjectConfiguration(path="htmlContentSimplifier.removeNavigation", package="NEOSidekick.MarkdownForAgents")
     * @var bool
     */
    protected bool $removeNavigation = true;

    /**
     * @Flow\InjectConfiguration(path="htmlContentSimplifier.removeLinks", package="NEOSidekick.MarkdownForAgents")
     * @var bool
     */
    protected bool $removeLinks = false;

    /**
     * @Flow\Inject(lazy = true)
     * @var Translator
     */
    protected $translator;

    public function simplify(string $html, ConversionOptions $options = new ConversionOptions()): string
    {
        $crawler = new Crawler();
        $crawler->addHtmlContent($html, 'UTF-8');

        $this->spaceOutLineBreaksInSingleLineContexts($crawler);
        // Source elements are removed later, so picture candidates must be
        // resolved while they are still available.
        $this->normalizeImageSources($crawler, $options);

        // Must run before the replacements below, so forms/iframes inside removed
        // or data-markdown-skip regions are never turned into links.
        $this->removeSelectors($crawler, $this->selectors($options));

        $this->replaceIframesWithLinks($crawler, $options);
        $this->replaceFormsWithLinks($crawler, $options);
        $this->normalizeAnchors($crawler);

        if (($options->keepEmptyAltImages ?? $this->keepEmptyAltImages) !== true) {
            $this->removeEmptyAltImages($crawler);
        }

        if (($options->removeLinks ?? $this->removeLinks) === true) {
            $this->removeHrefAttributes($crawler);
        }

        $body = $crawler->filter('body');
        if ($body->count() > 0) {
            $html = $body->html('');
        } else {
            $html = $crawler->html('');
        }

        foreach (array_merge($this->tagSeparatorAfter, $options->tagSeparatorAfter) as $tag => $separator) {
            if ($separator !== '') {
                $html = str_replace("/$tag><", "/$tag>$separator<", $html);
            }
        }

        return trim($html);
    }

    /**
     * @return array<int, string>
     */
    private function selectors(ConversionOptions $options): array
    {
        $removeSelectors = array_merge($this->removeSelectors, $options->removeSelectors);
        $selectors = $this->enabledSelectors($removeSelectors);

        if (($options->removeNavigation ?? $this->removeNavigation) === true) {
            $selectors = array_merge($selectors, $this->enabledSelectors($this->navigationSelectors));
        }

        return array_values(array_unique($selectors));
    }

    /**
     * Accepts either a map of selector => bool or a plain list of selectors.
     *
     * @param array<string|int, string|bool|null> $config
     * @return array<int, string>
     */
    private function enabledSelectors(array $config): array
    {
        $selectors = [];
        foreach ($config as $key => $value) {
            $selector = is_int($key) ? $value : $key;
            $enabled = is_int($key) ? ($value !== null && $value !== '') : (bool)$value;
            if ($enabled && is_string($selector) && trim($selector) !== '') {
                $selectors[] = $selector;
            }
        }

        return $selectors;
    }

    /**
     * @param array<int, string> $selectors
     */
    private function removeSelectors(Crawler $crawler, array $selectors): void
    {
        foreach ($selectors as $selector) {
            try {
                $crawler->filter($selector)->each(function (Crawler $nodes): void {
                    foreach ($nodes as $node) {
                        if ($node instanceof \DOMElement && strtolower($node->tagName) === 'source') {
                            // Keep parser-attached fallback content below HTML5
                            // source elements while dropping the source itself.
                            $this->unwrapNode($node);
                            continue;
                        }

                        $this->removeNode($node);
                    }
                });
            } catch (\InvalidArgumentException $exception) {
                continue;
            }
        }
    }

    private function replaceIframesWithLinks(Crawler $crawler, ConversionOptions $options): void
    {
        $iframes = $crawler->filter('iframe');
        if ($iframes->count() === 0) {
            return;
        }

        $fallbackLabel = $this->labelOption($options->iframeFallbackLabel, 'iframeFallback', 'Embedded content');

        $iframes->each(function (Crawler $node) use ($fallbackLabel): void {
            $domNode = $node->getNode(0);
            if (!$domNode instanceof \DOMElement || $domNode->parentNode === null) {
                return;
            }

            $src = trim($domNode->getAttribute('src'));
            if (!$this->isFollowableUrl($src)) {
                $this->removeNode($domNode);
                return;
            }

            $label = trim($domNode->getAttribute('title'));
            if ($label === '') {
                $label = trim($domNode->getAttribute('aria-label'));
            }
            if ($label === '') {
                $label = $fallbackLabel;
            }

            $this->replaceWithLink($domNode, $src, $label);
        });
    }

    private function replaceFormsWithLinks(Crawler $crawler, ConversionOptions $options): void
    {
        $forms = $crawler->filter('form');
        if ($forms->count() === 0) {
            return;
        }

        $pageUrl = $options->canonicalUri;
        $label = $this->labelOption($options->formNoticeLabel, 'formNotice', 'A form can be found at');

        $forms->each(function (Crawler $node) use ($pageUrl, $label): void {
            $domNode = $node->getNode(0);
            if (!$domNode instanceof \DOMElement || $domNode->parentNode === null) {
                return;
            }

            if ($pageUrl === '') {
                $this->removeNode($domNode);
                return;
            }

            $this->replaceWithLink($domNode, $pageUrl, $label);
        });
    }

    /**
     * Wraps the link in a <p> so the converter renders it as a standalone line.
     */
    private function replaceWithLink(\DOMElement $node, string $href, string $label): void
    {
        $document = $node->ownerDocument;
        if ($document === null || $node->parentNode === null) {
            return;
        }

        $anchor = $document->createElement('a');
        $anchor->setAttribute('href', $href);
        $anchor->appendChild($document->createTextNode($label));

        $paragraph = $document->createElement('p');
        $paragraph->appendChild($anchor);

        $node->parentNode->replaceChild($paragraph, $node);
    }

    /**
     * Unwraps anchors without a followable href; labels icon-only links (emptied
     * once their <svg> was removed) from their title/aria-label.
     */
    private function normalizeAnchors(Crawler $crawler): void
    {
        $crawler->filter('a')->each(function (Crawler $node): void {
            $domNode = $node->getNode(0);
            if (!$domNode instanceof \DOMElement) {
                return;
            }

            if (!$this->isFollowableUrl(trim($domNode->getAttribute('href')))) {
                $this->unwrapNode($domNode);
                return;
            }

            if (trim($domNode->textContent) !== '' || $domNode->getElementsByTagName('*')->length > 0) {
                return;
            }

            $label = trim($domNode->getAttribute('title'));
            if ($label === '') {
                $label = trim($domNode->getAttribute('aria-label'));
            }
            if ($label !== '' && $domNode->ownerDocument !== null) {
                $domNode->removeAttribute('title');
                $domNode->appendChild($domNode->ownerDocument->createTextNode($label));
            }
        });
    }

    private function unwrapNode(\DOMElement $node): void
    {
        $parent = $node->parentNode;
        if ($parent === null) {
            return;
        }

        while ($node->firstChild !== null) {
            $parent->insertBefore($node->firstChild, $node);
        }

        $parent->removeChild($node);
    }

    private function isFollowableUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $lowerUrl = strtolower($url);
        foreach (['javascript:', 'about:', 'data:'] as $scheme) {
            if (str_starts_with($lowerUrl, $scheme)) {
                return false;
            }
        }

        return true;
    }

    /** Caller-provided label, else the package's own translation, else the English fallback. */
    private function labelOption(string $explicit, string $translationId, string $fallback): string
    {
        if (trim($explicit) !== '') {
            return $explicit;
        }

        return $this->translate($translationId, $fallback);
    }

    private function translate(string $id, string $fallback): string
    {
        if ($this->translator === null) {
            return $fallback;
        }

        try {
            $translated = $this->translator->translateById($id, [], null, null, 'Markdown', 'NEOSidekick.MarkdownForAgents');
        } catch (\Throwable $exception) {
            return $fallback;
        }

        return is_string($translated) && $translated !== '' ? $translated : $fallback;
    }

    private function removeHrefAttributes(Crawler $crawler): void
    {
        $crawler->filter('a')->each(static function (Crawler $node): void {
            $domNode = $node->getNode(0);
            if ($domNode instanceof \DOMElement) {
                $domNode->removeAttribute('href');
            }
        });
    }

    private function normalizeImageSources(Crawler $crawler, ConversionOptions $options): void
    {
        $sourcePreference = $this->imageSourcePreference($options);
        if ($sourcePreference === []) {
            return;
        }

        $srcsetMaxCandidateWidth = $options->srcsetMaxCandidateWidth ?? $this->srcsetMaxCandidateWidth;

        $crawler->filter('img')->each(function (Crawler $node) use ($sourcePreference, $srcsetMaxCandidateWidth): void {
            $domNode = $node->getNode(0);
            if (!$domNode instanceof \DOMElement) {
                return;
            }

            $source = $this->preferredImageSource($domNode, $sourcePreference, $srcsetMaxCandidateWidth);
            if ($source !== '') {
                $domNode->setAttribute('src', $source);
            }
        });
    }

    /**
     * @return array<int, string>
     */
    private function imageSourcePreference(ConversionOptions $options): array
    {
        $sourcePreference = $this->imageSourcePreference;
        foreach ($options->imageSourcePreference as $source => $enabled) {
            $sourcePreference[$source] = $enabled;
        }

        return $this->enabledImageSources($sourcePreference);
    }

    /**
     * @param array<string, bool|null> $config
     * @return array<int, string>
     */
    private function enabledImageSources(array $config): array
    {
        $sources = [];
        foreach ($config as $source => $enabled) {
            if (!is_string($source)) {
                continue;
            }

            if ($enabled && trim($source) !== '') {
                $sources[] = $source;
            }
        }

        return array_values(array_unique($sources));
    }

    /**
     * @param array<int, string> $sourcePreference
     */
    private function preferredImageSource(\DOMElement $image, array $sourcePreference, int $srcsetMaxCandidateWidth): string
    {
        foreach ($sourcePreference as $source) {
            if ($this->isSrcsetSource($source)) {
                $url = $this->imageSourceFromSrcset(
                    $this->srcsetValueForImage($image, $source),
                    $srcsetMaxCandidateWidth
                );
            } else {
                $url = trim($image->getAttribute($source));
            }

            if ($this->isFollowableUrl($url)) {
                return $url;
            }
        }

        return '';
    }

    private function srcsetValueForImage(\DOMElement $image, string $source): string
    {
        $srcset = trim($image->getAttribute($source));
        if ($srcset !== '') {
            return $srcset;
        }

        $parent = $image->parentNode;
        if ($parent instanceof \DOMElement && strtolower($parent->tagName) === 'source') {
            $srcset = trim($parent->getAttribute($source));
            if ($srcset !== '') {
                return $srcset;
            }

            $parent = $parent->parentNode;
        }

        if (!$parent instanceof \DOMElement || strtolower($parent->tagName) !== 'picture') {
            return '';
        }

        $srcsets = [];
        foreach ($parent->childNodes as $child) {
            if (!$child instanceof \DOMElement || strtolower($child->tagName) !== 'source') {
                continue;
            }

            $srcset = trim($child->getAttribute($source));
            if ($srcset !== '') {
                $srcsets[] = $srcset;
            }
        }

        return implode(', ', $srcsets);
    }

    private function isSrcsetSource(string $source): bool
    {
        return $source === 'srcset' || str_ends_with($source, '-srcset');
    }

    private function imageSourceFromSrcset(string $srcset, int $srcsetMaxCandidateWidth): string
    {
        $candidates = $this->srcsetCandidates($srcset);
        if ($candidates === []) {
            return '';
        }

        $widthCandidates = array_values(array_filter($candidates, static fn (array $candidate): bool => isset($candidate['width'])));
        if ($widthCandidates !== []) {
            usort($widthCandidates, static fn (array $a, array $b): int => $a['width'] <=> $b['width']);
            if ($srcsetMaxCandidateWidth <= 0) {
                return (string)$widthCandidates[array_key_last($widthCandidates)]['url'];
            }

            $largerFallback = '';
            for ($index = count($widthCandidates) - 1; $index >= 0; $index--) {
                $candidate = $widthCandidates[$index];
                if ($candidate['width'] <= $srcsetMaxCandidateWidth) {
                    return (string)$candidate['url'];
                }

                $largerFallback = (string)$candidate['url'];
            }

            return $largerFallback;
        }

        $densityCandidates = array_values(array_filter($candidates, static fn (array $candidate): bool => isset($candidate['density'])));
        if ($densityCandidates !== []) {
            usort($densityCandidates, static fn (array $a, array $b): int => $a['density'] <=> $b['density']);
            return (string)$densityCandidates[array_key_last($densityCandidates)]['url'];
        }

        return (string)$candidates[0]['url'];
    }

    /**
     * @return array<int, array{url: string, width?: int, density?: float}>
     */
    private function srcsetCandidates(string $srcset): array
    {
        $candidates = [];
        foreach ($this->splitSrcsetCandidates($srcset) as $candidate) {
            if (preg_match('/^(?<url>.+?)\s+(?<descriptor>\d+w|\d+(?:\.\d+)?x)\s*$/', $candidate, $matches) === 1) {
                $url = trim($matches['url']);
                if (!$this->isUsefulSrcsetUrl($url)) {
                    continue;
                }

                $descriptor = $matches['descriptor'];
                if (str_ends_with($descriptor, 'w')) {
                    $candidates[] = ['url' => $url, 'width' => (int)substr($descriptor, 0, -1)];
                } else {
                    $candidates[] = ['url' => $url, 'density' => (float)substr($descriptor, 0, -1)];
                }
            } else {
                $url = $this->firstUsefulUrl($candidate);
                if ($url !== '') {
                    $candidates[] = ['url' => $url];
                }
            }
        }

        return $candidates;
    }

    /**
     * @return array<int, string>
     */
    private function splitSrcsetCandidates(string $srcset): array
    {
        $candidates = [];
        $candidate = '';
        $length = strlen($srcset);
        for ($index = 0; $index < $length; $index++) {
            $character = $srcset[$index];
            if ($character === ',' && $this->endsWithSrcsetDescriptor($candidate)) {
                $candidates[] = trim($candidate);
                $candidate = '';
                continue;
            }

            $candidate .= $character;
        }

        $candidate = trim($candidate);
        if ($candidate !== '') {
            $candidates[] = $candidate;
        }

        return $candidates;
    }

    private function endsWithSrcsetDescriptor(string $candidate): bool
    {
        // A comma is a separator only after a complete candidate; URLs may
        // contain commas themselves, for example in CDN transform paths.
        return preg_match('/\s(?:\d+w|\d+(?:\.\d+)?x)\s*$/', $candidate) === 1;
    }

    private function firstUsefulUrl(string $value): string
    {
        $value = trim($value);
        if ($this->isUsefulSrcsetUrl($value)) {
            return $value;
        }

        foreach (preg_split('/[\s,]+/', $value) ?: [] as $part) {
            $url = trim($part, " \t\n\r\0\x0B,");
            if ($this->isUsefulSrcsetUrl($url)) {
                return $url;
            }
        }

        return '';
    }

    private function isUsefulSrcsetUrl(string $url): bool
    {
        if (!$this->isFollowableUrl($url) || preg_match('/\s/', $url) === 1) {
            return false;
        }

        return preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) === 1
            || str_contains($url, '/')
            || str_contains($url, '.');
    }

    private function removeEmptyAltImages(Crawler $crawler): void
    {
        // Images without alt text are decorative noise for agents.
        $crawler->filter('img')->each(function (Crawler $node): void {
            $domNode = $node->getNode(0);
            if ($domNode !== null && trim($domNode->getAttribute('alt')) === '') {
                $this->removeNode($domNode);
            }
        });
    }

    private function removeNode(DOMNode $node): void
    {
        if ($node->parentNode === null || $node->nodeName === 'body') {
            return;
        }

        $node->parentNode->removeChild($node);
    }

    private function spaceOutLineBreaksInSingleLineContexts(Crawler $crawler): void
    {
        $crawler->filter('h1, h2, h3, h4, h5, h6, a, td, th, caption, dt, summary')->each(function (Crawler $context): void {
            $context->filter('br')->each(function (Crawler $node): void {
                $br = $node->getNode(0);
                if ($br instanceof \DOMElement && $br->parentNode !== null && $br->ownerDocument !== null) {
                    $br->parentNode->replaceChild($br->ownerDocument->createTextNode(' '), $br);
                }
            });
        });
    }
}
