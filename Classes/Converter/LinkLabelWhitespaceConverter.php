<?php

declare(strict_types=1);

namespace NEOSidekick\MarkdownForAgents\Converter;

use League\HTMLToMarkdown\Converter\LinkConverter;
use League\HTMLToMarkdown\ElementInterface;

final class LinkLabelWhitespaceConverter extends LinkConverter
{
    public function convert(ElementInterface $element): string
    {
        return $this->normalizeOuterLinkLabel(parent::convert($element));
    }

    private function normalizeOuterLinkLabel(string $markdown): string
    {
        if (!str_starts_with($markdown, '[')) {
            return $markdown;
        }

        $labelEnd = $this->outerLinkLabelEnd($markdown);
        if ($labelEnd === null) {
            return $markdown;
        }

        // Block-style anchors must still become one Markdown link label.
        $label = substr($markdown, 1, $labelEnd - 1);
        $normalizedLabel = preg_replace('/(?:[ \t]*\R[ \t]*)+/u', ' ', $label) ?? $label;
        $normalizedLabel = preg_replace('/(\]\([^)]+\))(?=\p{L}|\p{N})/u', '$1 ', $normalizedLabel)
            ?? $normalizedLabel;
        $normalizedLabel = preg_replace('/[ \t]{2,}/', ' ', $normalizedLabel) ?? $normalizedLabel;
        $normalizedLabel = trim($normalizedLabel);

        if ($normalizedLabel === $label) {
            return $markdown;
        }

        return '[' . $normalizedLabel . substr($markdown, $labelEnd);
    }

    private function outerLinkLabelEnd(string $markdown): ?int
    {
        $depth = 1;
        $length = strlen($markdown);

        for ($index = 1; $index < $length - 1; $index++) {
            $character = $markdown[$index];
            if ($character === '\\') {
                $index++;
                continue;
            }

            if ($character === '[') {
                $depth++;
                continue;
            }

            if ($character !== ']') {
                continue;
            }

            $depth--;
            if ($depth === 0 && $markdown[$index + 1] === '(') {
                return $index;
            }
        }

        return null;
    }
}
