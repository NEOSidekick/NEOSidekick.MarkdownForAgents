<?php

declare(strict_types=1);

namespace NEOSidekick\MarkdownForAgents\Tests\Unit\Fusion;

use Neos\Fusion\Core\Runtime;

final class MarkdownRendererTestRuntime extends Runtime
{
    private array $values;

    private array $canRenderMap;

    private array $renderMap;

    private array $exceptions;

    public function __construct(array $values, array $canRenderMap, array $renderMap = [], array $exceptions = [], bool $throwOnContentCacheRead = false)
    {
        $this->values = $values;
        $this->canRenderMap = $canRenderMap;
        $this->renderMap = $renderMap;
        $this->exceptions = $exceptions;
        $this->runtimeContentCache = new class ($throwOnContentCacheRead) {
            private bool $enabled = true;

            private bool $throwOnRead;

            public function __construct(bool $throwOnRead)
            {
                $this->throwOnRead = $throwOnRead;
            }

            public function setEnableContentCache($enabled): void
            {
                $this->enabled = (bool)$enabled;
            }

            public function getEnableContentCache(): bool
            {
                if ($this->throwOnRead) {
                    throw new \RuntimeException('Runtime internals changed');
                }

                return $this->enabled;
            }
        };
    }

    public function evaluate(string $fusionPath, $contextObject = null, string $behaviorIfPathNotFound = self::BEHAVIOR_RETURNNULL)
    {
        $propertyName = str_replace('/renderer/', '', $fusionPath);
        return $this->values[$propertyName] ?? null;
    }

    public function canRender($fusionPath)
    {
        return $this->canRenderMap[$fusionPath] ?? false;
    }

    public function render($fusionPath)
    {
        if (isset($this->exceptions[$fusionPath])) {
            throw $this->exceptions[$fusionPath];
        }

        return $this->renderMap[$fusionPath] ?? '';
    }

    public function setEnableContentCache($flag)
    {
        $this->runtimeContentCache->setEnableContentCache($flag);
    }
}
