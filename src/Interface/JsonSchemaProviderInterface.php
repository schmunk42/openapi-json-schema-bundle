<?php
// file generated with AI assistance: Claude Code - 2025-11-22 00:36:00

declare(strict_types=1);

namespace Schmunk42\OpenApiJsonSchema\Interface;

/**
 * Interface for entities that need to provide custom JSON schemas for their fields
 *
 * Entities implementing this interface can provide dynamic schema generation
 * instead of relying on static schema files.
 *
 * Use cases:
 * - Schemas that combine multiple sources (e.g., SchemaRegistry for anyOf)
 * - Runtime-generated schemas based on configuration
 * - Schemas that need access to services or business logic
 */
interface JsonSchemaProviderInterface
{
    /**
     * Get the JSON schema for a specific field
     *
     * @param string $fieldName The field name (without _json suffix)
     * @return array|null The JSON schema definition, or null if no custom schema
     */
    public static function getJsonSchema(string $fieldName): ?array;
}
