<?php
// file generated with AI assistance: Claude Code - 2025-11-22 00:36:00

declare(strict_types=1);

namespace Schmunk42\OpenApiJsonSchema\Attribute;

use Attribute;

/**
 * Marks a JSON field for automatic JSON Schema integration in OpenAPI.
 *
 * When applied to an entity property, the JsonFieldSchemaDecorator will:
 * 1. Check if entity implements JsonSchemaProviderInterface for dynamic schema generation
 * 2. Otherwise load schema from configured schema base path: {schemaBasePath}/{entity}-{schemaName}.json
 *
 * Example usage:
 *
 * #[ORM\Column(type: Types::JSON)]
 * #[JsonSchema(schemaName: 'metadata')]
 * private array $metadataJson = [];
 *
 * This will load schema from: {schemaBasePath}/customer-metadata.json
 *
 * If schemaName is omitted, it will be derived from the property name:
 * - metadataJson -> metadata
 * - configJson -> config
 * - settingsJson -> settings
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class JsonSchema
{
    public function __construct(
        public readonly ?string $schemaName = null
    ) {
    }
}
