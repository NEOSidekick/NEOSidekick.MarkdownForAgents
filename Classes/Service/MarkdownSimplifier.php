<?php

declare(strict_types=1);

namespace NEOSidekick\MarkdownForAgents\Service;

final class MarkdownSimplifier
{
    public function simplify(string $markdown): string
    {
        $markdown = html_entity_decode($markdown, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $markdown = str_replace("\xc2\xa0", ' ', $markdown);
        $markdown = preg_replace('/[ \t]+\n/', "\n", $markdown) ?? $markdown;
        $markdown = preg_replace('/([\w]{2}|[^\w\s])(#{1,6} )/', "$1\n\n$2", $markdown) ?? $markdown;
        $markdown = preg_replace('/\n{3,}/', "\n\n", $markdown) ?? $markdown;
        $markdown = preg_replace('/^[-\>][ \t]*\n/m', '', $markdown) ?? $markdown;

        return trim($markdown);
    }
}
