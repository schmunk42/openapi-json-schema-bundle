<?php
// file generated with AI assistance: Claude Code - 2025-11-22 00:36:00

declare(strict_types=1);

namespace Schmunk42\OpenApiJsonSchema\Interface;

/**
 * Interface for schema sources that can be registered with SchemaRegistry
 *
 * This interface allows any component (extensions, plugins, modules) to provide
 * JSON schemas that will be combined into a unified schema by SchemaRegistry.
 *
 * Typical use case: Multiple API client extensions each providing their own
 * configuration schema, which are combined into a single anyOf schema.
 *
 * Example:
 * ```php
 * class GitHubExtension implements SchemaProviderInterface
 * {
 *     public function getName(): string
 *     {
 *         return 'github';
 *     }
 *
 *     public function getSchemaPath(): string
 *     {
 *         return __DIR__ . '/../schema.json';
 *     }
 * }
 * ```
 */
interface SchemaProviderInterface
{
    /**
     * Get a unique name for this schema provider
     *
     * @return string Unique identifier (e.g., 'basecamp2', 'github', 'gitlab')
     */
    public function getName(): string;

    /**
     * Get the absolute path to the JSON schema file
     *
     * @return string Absolute path to schema.json file
     */
    public function getSchemaPath(): string;
}
