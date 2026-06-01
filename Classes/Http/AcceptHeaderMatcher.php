<?php

declare(strict_types=1);

namespace NEOSidekick\MarkdownForAgents\Http;

final class AcceptHeaderMatcher
{
    public function prefersMarkdown(string $acceptHeader, bool $allowPlainText = true): bool
    {
        $acceptHeader = trim($acceptHeader);
        if ($acceptHeader === '') {
            return false;
        }

        $markdown = null;
        $html = null;

        foreach (explode(',', $acceptHeader) as $index => $part) {
            $entry = $this->parseAcceptPart($part, $index);
            if ($entry === null) {
                continue;
            }

            if ($this->isMarkdownMediaType($entry['mediaType'], $allowPlainText)) {
                $markdown = $this->pickBetter($markdown, $entry);
            }

            if ($this->mediaRangeMatches($entry['mediaType'], 'text/html')) {
                $html = $this->pickBetter($html, $entry);
            }
        }

        if ($markdown === null || $markdown['quality'] <= 0.0) {
            return false;
        }

        if ($html === null || $html['quality'] <= 0.0) {
            return true;
        }

        if ($markdown['quality'] > $html['quality']) {
            return true;
        }

        if ($markdown['quality'] < $html['quality']) {
            return false;
        }

        return $markdown['index'] < $html['index'];
    }

    public function acceptsHtml(string $acceptHeader): bool
    {
        foreach (explode(',', $acceptHeader) as $index => $part) {
            $entry = $this->parseAcceptPart($part, $index);
            if ($entry !== null && $entry['quality'] > 0.0 && $this->mediaRangeMatches($entry['mediaType'], 'text/html')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{mediaType: string, quality: float, index: int}|null
     */
    private function parseAcceptPart(string $part, int $index): ?array
    {
        $segments = array_map('trim', explode(';', $part));
        $mediaType = strtolower(array_shift($segments) ?? '');
        if ($mediaType === '') {
            return null;
        }

        $quality = 1.0;
        foreach ($segments as $segment) {
            if (str_starts_with($segment, 'q=')) {
                $quality = max(0.0, min(1.0, (float)substr($segment, 2)));
                break;
            }
        }

        return [
            'mediaType' => $mediaType,
            'quality' => $quality,
            'index' => $index,
        ];
    }

    /**
     * @param array{mediaType: string, quality: float, index: int}|null $current
     * @param array{mediaType: string, quality: float, index: int} $candidate
     * @return array{mediaType: string, quality: float, index: int}
     */
    private function pickBetter(?array $current, array $candidate): array
    {
        if ($current === null) {
            return $candidate;
        }

        if ($candidate['quality'] > $current['quality']) {
            return $candidate;
        }

        if ($candidate['quality'] === $current['quality'] && $candidate['index'] < $current['index']) {
            return $candidate;
        }

        return $current;
    }

    private function isMarkdownMediaType(string $mediaType, bool $allowPlainText): bool
    {
        return $mediaType === 'text/markdown' || ($allowPlainText && $mediaType === 'text/plain');
    }

    private function mediaRangeMatches(string $range, string $mime): bool
    {
        if ($range === '*/*' || $range === $mime) {
            return true;
        }

        [$rangeType, $rangeSubtype] = array_pad(explode('/', $range, 2), 2, '');
        [$mimeType] = array_pad(explode('/', $mime, 2), 2, '');

        return $rangeSubtype === '*' && $rangeType === $mimeType;
    }
}
