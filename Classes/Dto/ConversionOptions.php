<?php

declare(strict_types=1);

namespace NEOSidekick\MarkdownForAgents\Dto;

/**
 * Typed, immutable options for a single Markdown conversion, parsed once from an
 * untyped array at the public boundary via {@see self::fromArray()}. A nullable
 * boolean means "not set for this call" and falls back to the package setting.
 */
final class ConversionOptions
{
    private const KNOWN_OPTIONS = [
        'canonicalUri',
        'formNoticeLabel',
        'iframeFallbackLabel',
        'removeNavigation',
        'removeLinks',
        'keepEmptyAltImages',
        'removeSelectors',
        'tagSeparatorAfter',
    ];

    /**
     * @param array<string, bool> $removeSelectors selector => keep/remove, merged over the package defaults
     * @param array<string, string> $tagSeparatorAfter tag => separator inserted after the closing tag, merged over the package defaults
     */
    public function __construct(
        public readonly string $canonicalUri = '',
        public readonly string $formNoticeLabel = '',
        public readonly string $iframeFallbackLabel = '',
        public readonly ?bool $removeNavigation = null,
        public readonly ?bool $removeLinks = null,
        public readonly ?bool $keepEmptyAltImages = null,
        public readonly array $removeSelectors = [],
        public readonly array $tagSeparatorAfter = [],
    ) {
    }

    /**
     * @param array<string, mixed> $options
     * @throws \InvalidArgumentException on unknown keys or mistyped values
     */
    public static function fromArray(array $options): self
    {
        $unknown = array_diff(array_keys($options), self::KNOWN_OPTIONS);
        if ($unknown !== []) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Unknown Markdown conversion option(s): %s. Known options: %s.',
                    implode(', ', $unknown),
                    implode(', ', self::KNOWN_OPTIONS)
                ),
                1769270001
            );
        }

        return new self(
            canonicalUri: self::readString($options, 'canonicalUri'),
            formNoticeLabel: self::readString($options, 'formNoticeLabel'),
            iframeFallbackLabel: self::readString($options, 'iframeFallbackLabel'),
            removeNavigation: self::readNullableBool($options, 'removeNavigation'),
            removeLinks: self::readNullableBool($options, 'removeLinks'),
            keepEmptyAltImages: self::readNullableBool($options, 'keepEmptyAltImages'),
            removeSelectors: self::readSelectorMap($options, 'removeSelectors'),
            tagSeparatorAfter: self::readStringMap($options, 'tagSeparatorAfter'),
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function readString(array $options, string $key): string
    {
        if (!array_key_exists($key, $options)) {
            return '';
        }

        $value = $options[$key];
        if (!is_string($value)) {
            throw new \InvalidArgumentException(
                sprintf('Markdown conversion option "%s" must be a string, %s given.', $key, get_debug_type($value)),
                1769270002
            );
        }

        return trim($value) === '' ? '' : $value;
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function readNullableBool(array $options, string $key): ?bool
    {
        if (!array_key_exists($key, $options) || $options[$key] === null) {
            return null;
        }

        $value = $options[$key];
        if (!is_bool($value)) {
            throw new \InvalidArgumentException(
                sprintf('Markdown conversion option "%s" must be a boolean, %s given.', $key, get_debug_type($value)),
                1769270003
            );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, bool>
     */
    private static function readSelectorMap(array $options, string $key): array
    {
        if (!array_key_exists($key, $options)) {
            return [];
        }

        $value = $options[$key];
        if (!is_array($value)) {
            throw new \InvalidArgumentException(
                sprintf('Markdown conversion option "%s" must be an array of "selector => bool", %s given.', $key, get_debug_type($value)),
                1769270004
            );
        }

        $map = [];
        foreach ($value as $selector => $enabled) {
            if (!is_string($selector)) {
                throw new \InvalidArgumentException(
                    sprintf('Markdown conversion option "%s" must be keyed by selector strings.', $key),
                    1769270005
                );
            }
            $map[$selector] = (bool)$enabled;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, string>
     */
    private static function readStringMap(array $options, string $key): array
    {
        if (!array_key_exists($key, $options)) {
            return [];
        }

        $value = $options[$key];
        if (!is_array($value)) {
            throw new \InvalidArgumentException(
                sprintf('Markdown conversion option "%s" must be an array of "tag => separator", %s given.', $key, get_debug_type($value)),
                1769270006
            );
        }

        $map = [];
        foreach ($value as $tag => $separator) {
            if (!is_string($tag) || !is_string($separator)) {
                throw new \InvalidArgumentException(
                    sprintf('Markdown conversion option "%s" must map tag strings to separator strings.', $key),
                    1769270007
                );
            }
            $map[$tag] = $separator;
        }

        return $map;
    }
}
