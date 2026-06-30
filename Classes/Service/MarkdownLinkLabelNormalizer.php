<?php

declare(strict_types=1);

namespace NEOSidekick\MarkdownForAgents\Service;

final class MarkdownLinkLabelNormalizer
{
    public function normalizeOuterLinkLabel(string $markdown): string
    {
        if (!str_starts_with($markdown, '[')) {
            return $markdown;
        }

        $labelEnd = $this->linkLabelEnd($markdown, 0);
        if ($labelEnd === null) {
            return $markdown;
        }

        // Block-style anchors must still become one Markdown link label.
        $label = substr($markdown, 1, $labelEnd - 1);
        $normalizedLabel = $this->normalizeLabel($label);

        if ($normalizedLabel === $label) {
            return $markdown;
        }

        return '[' . $normalizedLabel . substr($markdown, $labelEnd);
    }

    public function normalizeLinksOutsideFencedCodeBlocks(string $markdown): string
    {
        $parts = preg_split('/(\R)/u', $markdown, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $this->normalizeLinks($markdown);
        }

        $normalized = '';
        $pendingMarkdown = '';
        $insideFence = false;
        $fenceCharacter = '';
        $fenceLength = 0;

        for ($index = 0; $index < count($parts); $index += 2) {
            $line = $parts[$index];
            $lineBreak = $parts[$index + 1] ?? '';
            $fullLine = $line . $lineBreak;

            if ($insideFence) {
                $normalized .= $fullLine;
                if ($this->isClosingFence($line, $fenceCharacter, $fenceLength)) {
                    $insideFence = false;
                }
                continue;
            }

            $openingFence = $this->openingFence($line);
            if ($openingFence !== null) {
                $normalized .= $this->normalizeLinks($pendingMarkdown) . $fullLine;
                $pendingMarkdown = '';
                [$fenceCharacter, $fenceLength] = $openingFence;
                $insideFence = true;
                continue;
            }

            $pendingMarkdown .= $fullLine;
        }

        return $normalized . $this->normalizeLinks($pendingMarkdown);
    }

    private function normalizeLinks(string $markdown): string
    {
        $normalized = '';
        $offset = 0;
        $length = strlen($markdown);

        while ($offset < $length) {
            $start = strpos($markdown, '[', $offset);
            if ($start === false) {
                return $normalized . substr($markdown, $offset);
            }

            if ($start > 0 && $markdown[$start - 1] === '!') {
                $normalized .= substr($markdown, $offset, $start + 1 - $offset);
                $offset = $start + 1;
                continue;
            }

            $labelEnd = $this->linkLabelEnd($markdown, $start);
            if ($labelEnd === null) {
                $normalized .= substr($markdown, $offset, $start + 1 - $offset);
                $offset = $start + 1;
                continue;
            }

            $destinationEnd = $this->linkDestinationEnd($markdown, $labelEnd + 1);
            if ($destinationEnd === null) {
                $normalized .= substr($markdown, $offset, $start + 1 - $offset);
                $offset = $start + 1;
                continue;
            }

            $linkMarkdown = substr($markdown, $start, $destinationEnd - $start + 1);
            $normalized .= substr($markdown, $offset, $start - $offset)
                . $this->normalizeOuterLinkLabel($linkMarkdown);
            $offset = $destinationEnd + 1;
        }

        return $normalized;
    }

    private function normalizeLabel(string $label): string
    {
        $normalizedLabel = preg_replace('/(^|\R)[ \t]{0,3}#{1,6}[ \t]+/u', '$1', $label) ?? $label;
        $normalizedLabel = preg_replace('/(?:[ \t]*\R[ \t]*)+/u', ' ', $normalizedLabel)
            ?? $normalizedLabel;
        $normalizedLabel = preg_replace('/(\]\([^)]+\))(?=\p{L}|\p{N})/u', '$1 ', $normalizedLabel)
            ?? $normalizedLabel;
        $normalizedLabel = preg_replace('/[ \t]{2,}/', ' ', $normalizedLabel) ?? $normalizedLabel;

        return trim($normalizedLabel);
    }

    private function linkLabelEnd(string $markdown, int $start): ?int
    {
        $depth = 1;
        $length = strlen($markdown);

        for ($index = $start + 1; $index < $length - 1; $index++) {
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

    private function linkDestinationEnd(string $markdown, int $start): ?int
    {
        if (($markdown[$start] ?? null) !== '(') {
            return null;
        }

        $depth = 1;
        $length = strlen($markdown);

        for ($index = $start + 1; $index < $length; $index++) {
            $character = $markdown[$index];
            if ($character === '\\') {
                $index++;
                continue;
            }

            if ($character === '(') {
                $depth++;
                continue;
            }

            if ($character !== ')') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @return array{0: string, 1: int}|null
     */
    private function openingFence(string $line): ?array
    {
        if (!preg_match('/^[ \t]{0,3}(`{3,}|~{3,})/', $line, $matches)) {
            return null;
        }

        return [$matches[1][0], strlen($matches[1])];
    }

    private function isClosingFence(string $line, string $fenceCharacter, int $fenceLength): bool
    {
        $quotedFence = preg_quote(str_repeat($fenceCharacter, $fenceLength), '/');

        return (bool)preg_match('/^[ \t]{0,3}' . $quotedFence . preg_quote($fenceCharacter, '/') . '*[ \t]*$/', $line);
    }
}
