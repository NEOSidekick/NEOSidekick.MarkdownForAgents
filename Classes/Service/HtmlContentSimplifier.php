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
}
