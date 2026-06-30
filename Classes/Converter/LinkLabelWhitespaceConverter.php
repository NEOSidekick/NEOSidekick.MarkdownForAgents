<?php

declare(strict_types=1);

namespace NEOSidekick\MarkdownForAgents\Converter;

use League\HTMLToMarkdown\Converter\LinkConverter;
use League\HTMLToMarkdown\ElementInterface;
use NEOSidekick\MarkdownForAgents\Service\MarkdownLinkLabelNormalizer;

final class LinkLabelWhitespaceConverter extends LinkConverter
{
    private ?MarkdownLinkLabelNormalizer $linkLabelNormalizer = null;

    public function convert(ElementInterface $element): string
    {
        return $this->linkLabelNormalizer()->normalizeOuterLinkLabel(parent::convert($element));
    }

    private function linkLabelNormalizer(): MarkdownLinkLabelNormalizer
    {
        return $this->linkLabelNormalizer ??= new MarkdownLinkLabelNormalizer();
    }
}
