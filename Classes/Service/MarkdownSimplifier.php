<?php

declare(strict_types=1);

namespace NEOSidekick\MarkdownForAgents\Service;

final class MarkdownSimplifier
{
    public function simplify(string $markdown): string
    {
        // Decode HTML entities (e.g. &amp;, &nbsp;, &quot;) into their UTF-8 characters
        $markdown = html_entity_decode($markdown, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Replace non-breaking spaces (U+00A0) with regular spaces
        $markdown = str_replace("\xc2\xa0", ' ', $markdown);

        // Remove trailing spaces and tabs at the end of lines
        $markdown = preg_replace('/[ \t]+\n/', "\n", $markdown) ?? $markdown;

        // Ensure headings are separated from preceding text by a blank line
        $markdown = preg_replace('/([^\r\n])\s*(#{1,6}\s)/', "$1\n\n$2", $markdown) ?? $markdown;

        // Collapse three or more consecutive line breaks into exactly two
        $markdown = preg_replace('/\n{3,}/', "\n\n", $markdown) ?? $markdown;

        // Remove empty list or blockquote markers such as "-", ">", "-   ", or ">   "
        $markdown = preg_replace('/^[-\>][ \t]*\n/m', '', $markdown) ?? $markdown;

        // Remove leading and trailing whitespace from the final result
        return trim($markdown);
    }
}
