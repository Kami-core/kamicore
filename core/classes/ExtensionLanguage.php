<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

namespace Core;

if (!defined('IN_KAMI')) {
    die();
}

final class ExtensionLanguage
{
    private const ROOT_KEYS = [
        'plugin',
        'content_types',
        'fields',
        'field_types',
        'settings',
        'handlers',
        'phrases',
    ];

    private const METADATA_KEYS = [
        'title',
        'description',
        'placeholder',
        'options',
    ];

    public static function validatePlugin(
        array $language,
        array $manifest,
        string $source
    ): void {
        self::assertKnownKeys($language, self::ROOT_KEYS, $source);

        $plugin = $language['plugin'] ?? null;
        if (!is_array($plugin)) {
            throw new \RuntimeException(
                "Missing plugin metadata object in {$source}."
            );
        }

        self::validateMetadata($plugin, "{$source}.plugin", ['title', 'description']);

        if (!is_string($plugin['title'] ?? null) || $plugin['title'] === '') {
            throw new \RuntimeException(
                "Missing plugin title in {$source}."
            );
        }

        self::validateMetadataMap(
            self::section($language, 'settings', $source),
            is_array($manifest['settings'] ?? null) ? $manifest['settings'] : [],
            "{$source}.settings"
        );

        self::validateHandlers(
            self::section($language, 'handlers', $source),
            is_array($manifest['config']['handlers'] ?? null)
                ? $manifest['config']['handlers']
                : [],
            "{$source}.handlers"
        );

        self::validateContentTypes(
            self::section($language, 'content_types', $source),
            is_array($manifest['content_types'] ?? null)
                ? $manifest['content_types']
                : [],
            "{$source}.content_types"
        );

        self::validateMetadataMap(
            self::section($language, 'fields', $source),
            is_array($manifest['fields'] ?? null) ? $manifest['fields'] : [],
            "{$source}.fields"
        );

        self::validateMetadataMap(
            self::section($language, 'field_types', $source),
            is_array($manifest['field_types'] ?? null) ? $manifest['field_types'] : [],
            "{$source}.field_types"
        );

        $phrases = self::section($language, 'phrases', $source);
        foreach ($phrases as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                throw new \RuntimeException(
                    "Phrase keys and values must be strings in {$source}.phrases."
                );
            }
        }
    }

    public static function resolvePluginLanguages(array $manifest): array
    {
        $defaultLanguage = (string) (
            $manifest['config']['default_language'] ?? 'en'
        );
        $files = is_array($manifest['language_files'] ?? null)
            ? $manifest['language_files']
            : [];
        $fallback = self::fromManifest($manifest);
        $default = self::merge(
            $fallback,
            is_array($files[$defaultLanguage] ?? null)
                ? $files[$defaultLanguage]
                : []
        );
        $languages = array_values(
            array_unique([$defaultLanguage, ...array_keys($files)])
        );
        $resolved = [];

        foreach ($languages as $language) {
            $resolved[(string) $language] = self::merge(
                $default,
                is_array($files[$language] ?? null) ? $files[$language] : []
            );
        }

        return $resolved;
    }

    public static function pluginTranslation(array $language): array
    {
        $plugin = is_array($language['plugin'] ?? null)
            ? $language['plugin']
            : [];
        $translation = [
            'title' => $plugin['title'] ?? '',
            'description' => $plugin['description'] ?? '',
        ];

        foreach (['handlers', 'settings', 'phrases'] as $section) {
            if (!empty($language[$section]) && is_array($language[$section])) {
                $translation[$section] = $language[$section];
            }
        }

        return $translation;
    }

    public static function contentTypeTranslation(
        string $systemName,
        array $language,
        array $declaration
    ): array {
        $node = $language['content_types'][$systemName] ?? [];
        $node = is_array($node) ? $node : [];
        $translation = self::compileMetadata($node, $declaration);
        $fields = is_array($declaration['schema']['fields'] ?? null)
            ? $declaration['schema']['fields']
            : [];

        foreach ($fields as $fieldName => $fieldDeclaration) {
            if (!is_string($fieldName) || !is_array($fieldDeclaration)) {
                continue;
            }

            $fieldNode = $node['fields'][$fieldName] ?? [];
            $fieldNode = is_array($fieldNode) ? $fieldNode : [];
            $fieldTranslation = self::compileMetadata(
                $fieldNode,
                $fieldDeclaration
            );

            if ($fieldTranslation !== []) {
                $translation['schema']['fields'][$fieldName] = $fieldTranslation;
            }
        }

        return $translation;
    }

    public static function fieldTranslation(
        string $contentType,
        string $fieldName,
        array $language,
        array $declaration
    ): array {
        $node = $language['content_types'][$contentType]['fields'][$fieldName]
            ?? [];

        return self::compileMetadata(
            is_array($node) ? $node : [],
            $declaration
        );
    }

    public static function standaloneFieldTranslation(
        string $fieldName,
        array $language,
        array $declaration
    ): array {
        $node = $language['fields'][$fieldName] ?? [];

        return self::compileMetadata(
            is_array($node) ? $node : [],
            $declaration
        );
    }

    public static function fieldTypeTranslation(
        string $typeName,
        array $language,
        array $declaration
    ): array {
        $node = $language['field_types'][$typeName] ?? [];

        return self::compileMetadata(
            is_array($node) ? $node : [],
            $declaration
        );
    }

    public static function overlay(
        array $structure,
        array $translation
    ): array {
        foreach ($translation as $key => $value) {
            if (
                $key === 'options'
                && is_array($value)
                && is_array($structure['options'] ?? null)
                && array_is_list($structure['options'])
            ) {
                foreach ($structure['options'] as &$option) {
                    if (!is_array($option) || !array_key_exists('value', $option)) {
                        continue;
                    }

                    $optionKey = (string) $option['value'];
                    if (isset($value[$optionKey]) && is_string($value[$optionKey])) {
                        $option['title'] = $value[$optionKey];
                    }
                }
                unset($option);
                continue;
            }

            if (
                is_array($value)
                && is_array($structure[$key] ?? null)
            ) {
                $structure[$key] = self::overlay($structure[$key], $value);
                continue;
            }

            $structure[$key] = $value;
        }

        return $structure;
    }

    private static function fromManifest(array $manifest): array
    {
        $info = is_array($manifest['info'] ?? null) ? $manifest['info'] : [];
        $language = [
            'plugin' => [
                'title' => $info['title']
                    ?? $info['name']
                    ?? '',
                'description' => $info['description'] ?? '',
            ],
            'content_types' => [],
            'fields' => [],
            'field_types' => [],
            'settings' => [],
            'handlers' => [],
            'phrases' => [],
        ];

        foreach ($manifest['settings'] ?? [] as $name => $declaration) {
            if (is_string($name) && is_array($declaration)) {
                $language['settings'][$name] =
                    self::metadataFromDeclaration($declaration);
            }
        }

        foreach ($manifest['config']['handlers'] ?? [] as $name => $handler) {
            if (!is_string($name) || !is_array($handler)) {
                continue;
            }

            $node = self::metadataFromDeclaration($handler);

            foreach ($handler['actions'] ?? [] as $actionName => $action) {
                if (is_string($actionName) && is_array($action)) {
                    $node['actions'][$actionName] =
                        self::metadataFromDeclaration($action);
                }
            }

            foreach ($handler['instance_params'] ?? [] as $paramName => $param) {
                if (is_string($paramName) && is_array($param)) {
                    $node['instance_params'][$paramName] =
                        self::metadataFromDeclaration($param);
                }
            }

            $language['handlers'][$name] = $node;
        }

        foreach ($manifest['content_types'] ?? [] as $name => $contentType) {
            if (!is_string($name) || !is_array($contentType)) {
                continue;
            }

            $node = self::metadataFromDeclaration($contentType);

            foreach ($contentType['schema']['fields'] ?? [] as $fieldName => $field) {
                if (is_string($fieldName) && is_array($field)) {
                    $node['fields'][$fieldName] =
                        self::metadataFromDeclaration($field);
                }
            }

            $language['content_types'][$name] = $node;
        }

        foreach ($manifest['fields'] ?? [] as $name => $field) {
            if (is_string($name) && is_array($field)) {
                $language['fields'][$name] = self::metadataFromDeclaration($field);
            }
        }

        foreach ($manifest['field_types'] ?? [] as $name => $fieldType) {
            if (is_string($name) && is_array($fieldType)) {
                $language['field_types'][$name] = self::metadataFromDeclaration($fieldType);
            }
        }

        return $language;
    }

    private static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (
                is_array($value)
                && is_array($base[$key] ?? null)
                && !array_is_list($value)
                && !array_is_list($base[$key])
            ) {
                $base[$key] = self::merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    private static function compileMetadata(
        array $node,
        array $declaration
    ): array {
        $translation = [];

        foreach (['title', 'description', 'placeholder'] as $key) {
            if (isset($node[$key]) && is_string($node[$key])) {
                $translation[$key] = $node[$key];
            }
        }

        if (is_array($node['options'] ?? null)) {
            foreach ($declaration['options'] ?? [] as $option) {
                if (!is_array($option) || !array_key_exists('value', $option)) {
                    continue;
                }

                $value = (string) $option['value'];
                if (isset($node['options'][$value])) {
                    $translation['options'][] = [
                        'value' => $value,
                        'title' => $node['options'][$value],
                    ];
                }
            }
        }

        return $translation;
    }

    private static function metadataFromDeclaration(array $declaration): array
    {
        $metadata = [];

        foreach (['title', 'description', 'placeholder'] as $key) {
            if (isset($declaration[$key]) && is_string($declaration[$key])) {
                $metadata[$key] = $declaration[$key];
            }
        }

        foreach ($declaration['options'] ?? [] as $option) {
            if (
                is_array($option)
                && array_key_exists('value', $option)
                && isset($option['title'])
                && is_string($option['title'])
            ) {
                $metadata['options'][(string) $option['value']] =
                    $option['title'];
            }
        }

        return $metadata;
    }

    private static function validateMetadataMap(
        array $data,
        array $declarations,
        string $path
    ): void {
        foreach ($data as $name => $metadata) {
            if (!is_string($name) || !is_array($metadata)) {
                throw new \RuntimeException("Invalid metadata object in {$path}.");
            }

            if (!isset($declarations[$name])) {
                self::warn("Unknown declaration {$path}.{$name}.");
                continue;
            }

            self::validateMetadata($metadata, "{$path}.{$name}");
            self::validateOptions(
                $metadata,
                is_array($declarations[$name]) ? $declarations[$name] : [],
                "{$path}.{$name}"
            );
        }
    }

    private static function validateHandlers(
        array $data,
        array $declarations,
        string $path
    ): void {
        foreach ($data as $name => $handler) {
            if (!is_string($name) || !is_array($handler)) {
                throw new \RuntimeException("Invalid handler object in {$path}.");
            }

            $declaration = $declarations[$name] ?? null;
            if (!is_array($declaration)) {
                self::warn("Unknown handler {$path}.{$name}.");
                continue;
            }

            self::validateMetadata(
                $handler,
                "{$path}.{$name}",
                ['title', 'description', 'actions', 'instance_params']
            );
            self::validateMetadataMap(
                self::section($handler, 'actions', "{$path}.{$name}"),
                is_array($declaration['actions'] ?? null)
                    ? $declaration['actions']
                    : [],
                "{$path}.{$name}.actions"
            );
            self::validateMetadataMap(
                self::section($handler, 'instance_params', "{$path}.{$name}"),
                is_array($declaration['instance_params'] ?? null)
                    ? $declaration['instance_params']
                    : [],
                "{$path}.{$name}.instance_params"
            );
        }
    }

    private static function validateContentTypes(
        array $data,
        array $declarations,
        string $path
    ): void {
        foreach ($data as $name => $contentType) {
            if (!is_string($name) || !is_array($contentType)) {
                throw new \RuntimeException("Invalid content type object in {$path}.");
            }

            $declaration = $declarations[$name] ?? null;
            if (!is_array($declaration)) {
                self::warn("Unknown content type {$path}.{$name}.");
                continue;
            }

            self::validateMetadata(
                $contentType,
                "{$path}.{$name}",
                ['title', 'description', 'fields']
            );
            self::validateMetadataMap(
                self::section($contentType, 'fields', "{$path}.{$name}"),
                is_array($declaration['schema']['fields'] ?? null)
                    ? $declaration['schema']['fields']
                    : [],
                "{$path}.{$name}.fields"
            );
        }
    }

    private static function validateOptions(
        array $metadata,
        array $declaration,
        string $path
    ): void {
        if (!isset($metadata['options'])) {
            return;
        }

        $known = [];
        foreach ($declaration['options'] ?? [] as $option) {
            if (is_array($option) && array_key_exists('value', $option)) {
                $known[(string) $option['value']] = true;
            }
        }

        foreach ($metadata['options'] as $value => $title) {
            if (!is_string($value) || !is_string($title)) {
                throw new \RuntimeException(
                    "Option keys and titles must be strings in {$path}.options."
                );
            }

            if (!isset($known[$value])) {
                self::warn("Unknown option {$path}.options.{$value}.");
            }
        }
    }

    private static function validateMetadata(
        array $metadata,
        string $path,
        ?array $allowed = null
    ): void {
        self::assertKnownKeys(
            $metadata,
            $allowed ?? self::METADATA_KEYS,
            $path
        );

        foreach (['title', 'description', 'placeholder'] as $key) {
            if (isset($metadata[$key]) && !is_string($metadata[$key])) {
                throw new \RuntimeException("{$path}.{$key} must be a string.");
            }
        }

        if (isset($metadata['options']) && !is_array($metadata['options'])) {
            throw new \RuntimeException("{$path}.options must be an object.");
        }
    }

    private static function assertKnownKeys(
        array $data,
        array $allowed,
        string $path
    ): void {
        foreach (array_keys($data) as $key) {
            if (!is_string($key) || !in_array($key, $allowed, true)) {
                throw new \RuntimeException("Unknown key {$path}.{$key}.");
            }
        }
    }

    private static function section(
        array $data,
        string $key,
        string $path
    ): array {
        if (!isset($data[$key])) {
            return [];
        }

        if (!is_array($data[$key])) {
            throw new \RuntimeException("{$path}.{$key} must be an object.");
        }

        return $data[$key];
    }

    private static function warn(string $message): void
    {
        trigger_error($message, E_USER_WARNING);
    }
}
