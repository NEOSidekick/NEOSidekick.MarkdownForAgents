<?php

declare(strict_types=1);

namespace NEOSidekick\MarkdownForAgents\Fusion;

use Neos\Neos\Fusion\ConvertUrisImplementation;

/**
 * Neos.Neos:ConvertUris resolves inline node links in the request format and has
 * no "format" option, so an agent-facing render (".md" Markdown or the "llms.txt"
 * format) emits links in that non-HTML format. This keeps them canonical HTML.
 */
final class HtmlFormatConvertUrisImplementation extends ConvertUrisImplementation
{
    public function evaluate()
    {
        $request = $this->runtime->getControllerContext()->getRequest()->getMainRequest();
        $previousFormat = $request->getFormat();

        if ($previousFormat === 'html') {
            return parent::evaluate();
        }

        $request->setFormat('html');

        try {
            return parent::evaluate();
        } finally {
            $request->setFormat($previousFormat);
        }
    }
}
