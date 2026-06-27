<?php

declare(strict_types=1);

namespace NEOSidekick\MarkdownForAgents\EelHelper;

use NEOSidekick\MarkdownForAgents\Dto\ConversionOptions;
use NEOSidekick\MarkdownForAgents\Service\MarkdownConverter;
use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Annotations as Flow;

final class MarkdownHelper implements ProtectedContextAwareInterface
{
    /**
     * @Flow\Inject
     * @var MarkdownConverter
     */
    protected $markdownConverter;

    public function __construct(?MarkdownConverter $markdownConverter = null)
    {
        $this->markdownConverter = $markdownConverter ?? new MarkdownConverter();
    }

    /**
     * @param string $html HTML input to simplify and convert
     * @param array<string, mixed> $options
     */
    public function htmlToMarkdown(string $html, array $options = []): string
    {
        return $this->markdownConverter->convert($html, ConversionOptions::fromArray($options));
    }

    public function allowsCallOfMethod($methodName): bool
    {
        return true;
    }
}
