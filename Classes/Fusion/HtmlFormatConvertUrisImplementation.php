<?php

declare(strict_types=1);

namespace NEOSidekick\MarkdownForAgents\Fusion;

use Neos\Neos\Fusion\ConvertUrisImplementation;

/**
 * Neos.Neos:ConvertUris resolves inline node links in the request format and has
 * no "format" option, so a Markdown render emits ".md" URLs. This keeps them
 * canonical HTML.
 */
final class HtmlFormatConvertUrisImplementation extends ConvertUrisImplementation
{
    public function evaluate()
    {
        $request = $this->runtime->getControllerContext()->getRequest()->getMainRequest();
        $previousFormat = $request->getFormat();

        // Only a Markdown render emits the ".md" links this corrects.
        if ($previousFormat !== 'markdown') {
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
