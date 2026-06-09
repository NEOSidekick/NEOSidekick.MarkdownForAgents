<?php

declare(strict_types=1);

namespace NEOSidekick\MarkdownForAgents\Service;

use League\HTMLToMarkdown\Converter\TableConverter;
use League\HTMLToMarkdown\HtmlConverter;
use NEOSidekick\MarkdownForAgents\Dto\ConversionOptions;

final class MarkdownConverter
{
    private HtmlContentSimplifier $htmlContentSimplifier;

    private MarkdownSimplifier $markdownSimplifier;

    public function __construct(
        ?HtmlContentSimplifier $htmlContentSimplifier = null,
        ?MarkdownSimplifier $markdownSimplifier = null
    ) {
        $this->htmlContentSimplifier = $htmlContentSimplifier ?? new HtmlContentSimplifier();
        $this->markdownSimplifier = $markdownSimplifier ?? new MarkdownSimplifier();
    }

    public function convert(string $html, ConversionOptions $options = new ConversionOptions()): string
    {
        $simplifiedHtml = $this->htmlContentSimplifier->simplify($html, $options);
        if ($simplifiedHtml === '') {
            return '';
        }

        $converter = new HtmlConverter([
            'strip_tags' => true,
            'hard_break' => false,
            'remove_nodes' => 'style script',
            'header_style' => 'atx',
        ]);
        $converter->getEnvironment()->addConverter(new TableConverter());

        return $this->markdownSimplifier->simplify($converter->convert($simplifiedHtml));
    }
}
