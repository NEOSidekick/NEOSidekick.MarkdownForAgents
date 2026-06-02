<?php

declare(strict_types=1);

namespace NEOSidekick\MarkdownForAgents\Fusion;

use GuzzleHttp\Psr7\Message;
use NEOSidekick\MarkdownForAgents\Service\MarkdownConverter;
use Neos\Flow\Annotations as Flow;
use Neos\Fusion\FusionObjects\AbstractFusionObject;
use Psr\Log\LoggerInterface;

final class MarkdownRendererImplementation extends AbstractFusionObject
{
    private const ERROR_OUTPUT_MARKERS = [
        'An exception was thrown while Neos tried to render your page',
        'Neos\\Flow\\Error\\DebugExceptionHandler',
        'Exception while rendering',
    ];

    /**
     * @Flow\Inject
     * @var MarkdownConverter
     */
    protected $markdownConverter;

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    public function evaluate(): string
    {
        try {
            return $this->renderMarkdown();
        } catch (\Throwable $exception) {
            $this->logRenderingFailure('Markdown rendering failed', $exception);
            return $this->renderErrorFallback();
        }
    }

    private function renderMarkdown(): string
    {
        $type = $this->fusionValue('type');
        if (!is_string($type) || $type === '') {
            return '';
        }

        $suffix = $this->fusionValue('suffix') ?: '.Markdown';
        $markdownType = $type . $suffix;

        if ($this->runtime->canRender('/type<' . $markdownType . '>')) {
            try {
                return trim($this->renderWithoutContentCache(sprintf('%s/element<%s>', $this->path, $markdownType)));
            } catch (\Throwable $exception) {
                $this->logRenderingFailure('Explicit Markdown Fusion prototype failed, trying HTML fallback', $exception);
                if ($this->fusionValue('fallbackToHtml') === false) {
                    return $this->renderErrorFallback();
                }
            }
        }

        if ($this->fusionValue('fallbackToHtml') === false) {
            return '';
        }

        // Render the whole element: Neos applies a document prototype's @context only
        // when the element itself is evaluated, not when rendering a sub-path.
        $fallbackPath = sprintf('%s/element<%s>', $this->path, $type);
        $html = $this->stripHttpMessageHead($this->renderWithoutContentCache($fallbackPath));
        $this->assertNoExceptionOutput($html);

        return $this->markdownConverter->convert($html, $this->conversionOptions());
    }

    /**
     * A rendered document is a serialized Neos.Fusion:Http.Message; return its body.
     */
    private function stripHttpMessageHead(string $output): string
    {
        if (strpos($output, 'HTTP/') !== 0) {
            return $output;
        }

        return (string)Message::parseResponse($output)->getBody();
    }

    /**
     * @return array<string, string>
     */
    private function conversionOptions(): array
    {
        return [
            'canonicalUri' => $this->safeFusionString('canonicalUri'),
            'formNoticeLabel' => $this->safeFusionString('formNoticeLabel'),
            'iframeFallbackLabel' => $this->safeFusionString('iframeFallbackLabel'),
        ];
    }

    private function renderWithoutContentCache(string $fusionPath): string
    {
        $contentCacheWasEnabled = $this->isContentCacheEnabled();
        $this->runtime->setEnableContentCache(false);

        try {
            return (string)$this->runtime->render($fusionPath);
        } finally {
            $this->runtime->setEnableContentCache($contentCacheWasEnabled);
        }
    }

    private function assertNoExceptionOutput(string $html): void
    {
        foreach (self::ERROR_OUTPUT_MARKERS as $marker) {
            if (str_contains($html, $marker)) {
                throw new \RuntimeException('Fusion returned HTML exception output while rendering Markdown fallback.', 1769010001);
            }
        }
    }

    private function renderErrorFallback(): string
    {
        $lines = [
            '# ' . ($this->safeFusionString('title') ?: 'Markdown rendering failed'),
            '',
            'This page could not be rendered as Markdown.',
        ];

        $canonicalUri = $this->safeFusionString('canonicalUri');
        if ($canonicalUri !== '') {
            $lines[] = '';
            $lines[] = 'Canonical: ' . $canonicalUri;
        }

        return trim(implode("\n", $lines));
    }

    private function safeFusionString(string $path): string
    {
        try {
            $value = $this->fusionValue($path);
        } catch (\Throwable $exception) {
            return '';
        }

        return is_string($value) ? trim($value) : '';
    }

    private function logRenderingFailure(string $message, \Throwable $exception): void
    {
        if (!$this->logger instanceof LoggerInterface) {
            return;
        }

        $this->logger->error($message, [
            'exception' => $exception,
            'fusionPath' => $this->path,
            'type' => $this->safeFusionString('type'),
        ]);
    }

    private function isContentCacheEnabled(): bool
    {
        // Fusion Runtime exposes no public getter for the content cache state,
        // so we read it defensively and assume disabled if the internals change.
        try {
            return (bool)(function () {
                return $this->runtimeContentCache->getEnableContentCache();
            })->call($this->runtime);
        } catch (\Throwable $exception) {
            return false;
        }
    }
}
