<?php

declare(strict_types=1);

namespace NEOSidekick\MarkdownForAgents\EelHelper;

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

    public function htmlToMarkdown(string $html, array $options = []): string
    {
        return $this->markdownConverter->convert($html, $options);
    }

    public function allowsCallOfMethod($methodName): bool
    {
        return true;
    }
}
