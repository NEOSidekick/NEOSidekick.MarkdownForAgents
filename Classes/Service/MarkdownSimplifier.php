<?php

declare(strict_types=1);

namespace NEOSidekick\MarkdownForAgents\Service;

final class MarkdownSimplifier
{
    private MarkdownLinkLabelNormalizer $linkLabelNormalizer;

    public function __construct(?MarkdownLinkLabelNormalizer $linkLabelNormalizer = null)
    {
        $this->linkLabelNormalizer = $linkLabelNormalizer ?? new MarkdownLinkLabelNormalizer();
    }

    public function simplify(string $markdown): string
    {
        // Decode HTML entities (e.g. &amp;, &nbsp;, &quot;) into their UTF-8 characters
        $markdown = html_entity_decode($markdown, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Replace non-breaking spaces (U+00A0) with regular spaces
        $markdown = str_replace("\xc2\xa0", ' ', $markdown);

        // Remove trailing spaces and tabs at the end of lines
        $markdown = preg_replace('/[ \t]+\n/', "\n", $markdown) ?? $markdown;

        $markdown = $this->normalizeHeadingsOutsideFencedCodeBlocks($markdown);

        // Card-like HTML often converts to adjacent Markdown links or images; keep them readable.
        $markdown = preg_replace('/([\p{L}\p{N}.:;?])(?=\[[^\]\n]+\]\()/u', '$1 ', $markdown) ?? $markdown;
        $markdown = preg_replace('/(\]\([^)]+\))(?=!\[|\[)/', "$1\n\n", $markdown) ?? $markdown;
        $markdown = preg_replace('/(\]\([^)]+\))(?=\p{L}|\p{N})/u', "$1\n\n", $markdown) ?? $markdown;
        $markdown = preg_replace('/(\p{L}|\p{N})(?=!\[)/u', "$1\n\n", $markdown) ?? $markdown;
        $markdown = preg_replace('/(\))(?=!\[)/', "$1\n\n", $markdown) ?? $markdown;

        // Collapse three or more consecutive line breaks into exactly two
        $markdown = preg_replace('/\n{3,}/', "\n\n", $markdown) ?? $markdown;

        // Remove empty list or blockquote markers such as "-", ">", "-   ", or ">   "
        $markdown = preg_replace('/^[-\>][ \t]*\n/m', '', $markdown) ?? $markdown;

        $markdown = $this->linkLabelNormalizer->normalizeLinksOutsideFencedCodeBlocks($markdown);

        // Remove leading and trailing whitespace from the final result
        return trim($markdown);
    }

    private function normalizeHeadingsOutsideFencedCodeBlocks(string $markdown): string
    {
        return $this->processOutsideFencedCodeBlocks($markdown, function (string $markdown): string {
            // Insert a blank line before a heading that is directly appended to text
            $markdown = preg_replace('/([\w]{2}|[^\w\s#])(#{1,6}\s)/', "$1\n\n$2", $markdown) ?? $markdown;

            // Ensure that every heading is preceded by a blank line unless one already exists
            $markdown = preg_replace('/([^\n])\n(#{1,6}\s.*)$/m', "$1\n\n$2", $markdown) ?? $markdown;

            // Ensure that every heading is followed by a blank line unless one already exists
            $markdown = preg_replace('/^(#{1,6}\s.*)\n([^\n#])/m', "$1\n\n$2", $markdown) ?? $markdown;

            // Empty ATX headings add outline noise without carrying document content.
            return preg_replace('/^[ ]{0,3}#{1,6}[ \t]*(?:\R|$)/m', '', $markdown) ?? $markdown;
        });
    }

    private function processOutsideFencedCodeBlocks(string $markdown, callable $processor): string
    {
        $parts = preg_split('/(\R)/u', $markdown, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $processor($markdown);
        }

        $normalized = '';
        $pendingMarkdown = '';
        $insideFence = false;
        $fenceCharacter = '';
        $fenceLength = 0;

        for ($index = 0; $index < count($parts); $index += 2) {
            $line = $parts[$index];
            $lineBreak = $parts[$index + 1] ?? '';

            if ($insideFence) {
                $normalized .= $line . $lineBreak;
                if ($this->isClosingFence($line, $fenceCharacter, $fenceLength)) {
                    $insideFence = false;
                }
                continue;
            }

            $openingFence = $this->openingFence($line);
            if ($openingFence !== null) {
                $normalized .= $processor($pendingMarkdown);
                $pendingMarkdown = '';
                [$fenceCharacter, $fenceLength] = $openingFence;
                $insideFence = true;
                $normalized .= $line . $lineBreak;
                continue;
            }

            $pendingMarkdown .= $line . $lineBreak;
        }

        return $normalized . $processor($pendingMarkdown);
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
