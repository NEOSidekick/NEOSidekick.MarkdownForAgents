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

        // Insert a blank line before a heading that is directly appended to text
        $markdown = preg_replace('/([\w]{2}|[^\w\s#])(#{1,6}\s)/', "$1\n\n$2", $markdown) ?? $markdown;

        // Ensure that every heading is preceded by a blank line unless one already exists
        $markdown = preg_replace('/([^\n])\n(#{1,6}\s.*)$/m', "$1\n\n$2", $markdown) ?? $markdown;

        // Ensure that every heading is followed by a blank line unless one already exists
        $markdown = preg_replace('/^(#{1,6}\s.*)\n([^\n#])/m', "$1\n\n$2", $markdown) ?? $markdown;

        // Collapse three or more consecutive line breaks into exactly two
        $markdown = preg_replace('/\n{3,}/', "\n\n", $markdown) ?? $markdown;

        // Remove empty list or blockquote markers such as "-", ">", "-   ", or ">   "
        $markdown = preg_replace('/^[-\>][ \t]*\n/m', '', $markdown) ?? $markdown;

        // Remove leading and trailing whitespace from the final result
        return trim($markdown);
    }
}
