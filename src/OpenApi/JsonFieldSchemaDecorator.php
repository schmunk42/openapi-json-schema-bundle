<?php
// file generated with AI assistance: Claude Code - 2025-11-22 00:38:00

declare(strict_types=1);

namespace Schmunk42\OpenApiJsonSchema\OpenApi;

use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\Operation;
use Schmunk42\OpenApiJsonSchema\Attribute\JsonSchema as JsonSchemaAttribute;
use Schmunk42\OpenApiJsonSchema\Interface\JsonSchemaProviderInterface;
use Schmunk42\OpenApiJsonSchema\Service\SchemaRegistry;
use Psr\Log\LoggerInterface;
use ReflectionClass;

/**
 * Generic decorator that dynamically injects JSON schemas for fields marked with #[JsonSchema] attribute.
 *
 * Usage:
 * 1. Add #[JsonSchema] attribute to JSON column properties
 * 2. Schema will be loaded from configured schema base path: {schemaBasePath}/{entity}-{schemaName}.json
 * 3. If entity implements JsonSchemaProviderInterface, dynamic generation is used instead
 *
 * Example:
 * #[ORM\Column(type: Types::JSON)]
 * #[JsonSchema(schemaName: 'metadata')]
 * private array $customerData = [];
 *
 * Schema file: {schemaBasePath}/customer-metadata.json
 */
class JsonFieldSchemaDecorator implements SchemaFactoryInterface
{
    public function __construct(
        private readonly SchemaFactoryInterface $decorated,
        private readonly SchemaRegistry $schemaRegistry,
        private readonly LoggerInterface $logger,
        private readonly string $schemaBasePath
    ) {
        // Store SchemaRegistry for entities that need dynamic schema generation
        // This allows entities implementing JsonSchemaProviderInterface to access
        // the registry for generating unified schemas (e.g., anyOf from multiple providers)
        self::$globalSchemaRegistry = $schemaRegistry;
    }

    /** @var SchemaRegistry|null Global registry for entities to access */
    private static ?SchemaRegistry $globalSchemaRegistry = null;

    /**
     * Get the global SchemaRegistry instance
     * For use by entities implementing JsonSchemaProviderInterface
     */
    public static function getSchemaRegistry(): ?SchemaRegistry
    {
        return self::$globalSchemaRegistry;
    }

    /**
     * Get schema name for a property by checking #[JsonSchema] attribute
     */
    private function getSchemaNameFromAttribute(string $className, string $propertyName): ?string
    {
        try {
            $reflectionClass = new ReflectionClass($className);

            if (!$reflectionClass->hasProperty($propertyName)) {
                return null;
            }

            $reflectionProperty = $reflectionClass->getProperty($propertyName);
            $attributes = $reflectionProperty->getAttributes(JsonSchemaAttribute::class);

            if (empty($attributes)) {
                return null;
            }

            $attribute = $attributes[0]->newInstance();

            // If schemaName is provided, use it; otherwise derive from property name
            if ($attribute->schemaName !== null) {
                return $attribute->schemaName;
            }

            // Derive schema name from property name
            // metadataJson -> metadata, configJson -> config
            $derivedName = $propertyName;
            if (str_ends_with($derivedName, 'Json')) {
                $derivedName = substr($derivedName, 0, -4);
            }

            // Convert camelCase to snake_case
            return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $derivedName));
        } catch (\ReflectionException $e) {
            $this->logger->warning('Failed to reflect property for JsonSchema attribute', [
                'className' => $className,
                'propertyName' => $propertyName,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function buildSchema(
        string $className,
        string $format = 'json',
        string $type = Schema::TYPE_OUTPUT,
        ?Operation $operation = null,
        ?Schema $schema = null,
        ?array $serializerContext = null,
        bool $forceCollection = false
    ): Schema {
        $schema = $this->decorated->buildSchema($className, $format, $type, $operation, $schema, $serializerContext, $forceCollection);

        // Extract short class name for schema file lookup
        $shortClassName = strtolower(substr($className, strrpos($className, '\\') + 1));

        // Also check root-level properties (not just definitions)
        $rootProperties = $schema['properties'] ?? [];

        $definitions = $schema->getDefinitions();

        // Process all definitions
        if ($definitions !== null) {
            foreach ($definitions as $key => $definition) {
                if (!isset($definition['properties'])) {
                    continue;
                }

                // Look for fields with #[JsonSchema] attribute
                foreach ($definition['properties'] as $propertyName => $propertyDef) {
                    // Check if property has #[JsonSchema] attribute
                    $schemaName = $this->getSchemaNameFromAttribute($className, $propertyName);

                    if ($schemaName === null) {
                        continue;
                    }

                    $jsonSchema = null;

                    // Check if entity implements JsonSchemaProviderInterface
                    if (is_subclass_of($className, JsonSchemaProviderInterface::class)) {
                        $jsonSchema = $className::getJsonSchema($schemaName);
                    }

                    // If no custom schema from entity, try loading from file
                    if ($jsonSchema === null) {
                        $schemaFile = rtrim($this->schemaBasePath, '/') . '/' . $shortClassName . '-' . $schemaName . '.json';

                        if (!file_exists($schemaFile)) {
                            $this->logger->debug('Schema file not found', [
                                'className' => $className,
                                'propertyName' => $propertyName,
                                'schemaName' => $schemaName,
                                'schemaFile' => $schemaFile
                            ]);
                            continue;
                        }

                        $jsonSchema = json_decode(file_get_contents($schemaFile), true);

                        if ($jsonSchema === null) {
                            $this->logger->warning('Failed to decode schema file', [
                                'schemaFile' => $schemaFile
                            ]);
                            continue;
                        }
                    }

                    // Replace the property definition with the schema
                    // Check if this is an anyOf-only schema (no type field)
                    if (isset($jsonSchema['anyOf']) && !isset($jsonSchema['type'])) {
                        // For anyOf-only schemas, completely replace with only description and anyOf
                        // This ensures no type, properties, required, or additionalProperties leak through
                        $definition['properties'][$propertyName] = [
                            'description' => $jsonSchema['description'] ?? '',
                            'anyOf' => $jsonSchema['anyOf']
                        ];

                        // Log what we're setting for anyOf schema
                        $this->logger->debug('Setting anyOf schema for property', [
                            'className' => $className,
                            'propertyName' => $propertyName,
                            'anyOfCount' => count($jsonSchema['anyOf']),
                            'firstAnyOf' => $jsonSchema['anyOf'][0] ?? null
                        ]);

                        // Explicitly remove any existing conflicting keys that might have been set by base factory
                        unset(
                            $definition['properties'][$propertyName]['type'],
                            $definition['properties'][$propertyName]['properties'],
                            $definition['properties'][$propertyName]['required'],
                            $definition['properties'][$propertyName]['additionalProperties']
                        );
                    } else {
                        // For regular object schemas, include all properties
                        $definition['properties'][$propertyName] = [
                            'type' => $jsonSchema['type'] ?? 'object',
                            'description' => $jsonSchema['description'] ?? '',
                            'properties' => $jsonSchema['properties'] ?? [],
                            'required' => $jsonSchema['required'] ?? [],
                            'additionalProperties' => $jsonSchema['additionalProperties'] ?? false
                        ];

                        // Handle anyOf schemas with type (edge case)
                        if (isset($jsonSchema['anyOf'])) {
                            $definition['properties'][$propertyName]['anyOf'] = $jsonSchema['anyOf'];
                        }
                    }

                    $definitions[$key] = $definition;
                }
            }
        } // End if ($definitions !== null)

        // Remove snake_case versions of *Json fields (keep only camelCase for API)
        if ($definitions !== null) {
            foreach ($definitions as $key => $definition) {
                if (!isset($definition['properties'])) {
                    continue;
                }

                $toRemove = [];
                foreach ($definition['properties'] as $propertyName => $propertyDef) {
                    // Check if this is a snake_case *_json field that has a camelCase version
                    if (str_ends_with($propertyName, '_json')) {
                        // Convert to camelCase equivalent (e.g., "metadata_json" -> "metadataJson")
                        $parts = explode('_', $propertyName);
                        $camelCase = $parts[0];
                        for ($i = 1; $i < count($parts); $i++) {
                            $camelCase .= ucfirst($parts[$i]);
                        }

                        if (isset($definition['properties'][$camelCase])) {
                            $toRemove[] = $propertyName;
                        }
                    }
                }

                // Remove the snake_case duplicates
                foreach ($toRemove as $prop) {
                    unset($definitions[$key]['properties'][$prop]);
                }
            }
        } // End if ($definitions !== null)

        // Process root-level properties as well (not in definitions)
        if (!empty($rootProperties)) {
            foreach ($rootProperties as $propertyName => $propertyDef) {
                // Check if property has #[JsonSchema] attribute
                $schemaName = $this->getSchemaNameFromAttribute($className, $propertyName);

                if ($schemaName === null) {
                    continue;
                }

                $jsonSchema = null;

                // Check if entity implements JsonSchemaProviderInterface
                if (is_subclass_of($className, JsonSchemaProviderInterface::class)) {
                    $jsonSchema = $className::getJsonSchema($schemaName);
                }

                // If no custom schema from entity, try loading from file
                if ($jsonSchema === null) {
                    $schemaFile = rtrim($this->schemaBasePath, '/') . '/' . $shortClassName . '-' . $schemaName . '.json';

                    if (file_exists($schemaFile)) {
                        $jsonSchema = json_decode(file_get_contents($schemaFile), true);
                    }
                }

                // Apply the schema from JSON file
                if ($jsonSchema !== null) {
                    // Check if this is an anyOf-only schema (no type field)
                    if (isset($jsonSchema['anyOf']) && !isset($jsonSchema['type'])) {
                        // For anyOf-only schemas, completely replace with only description and anyOf
                        // This ensures no type, properties, required, or additionalProperties leak through
                        $rootProperties[$propertyName] = [
                            'description' => $jsonSchema['description'] ?? '',
                            'anyOf' => $jsonSchema['anyOf']
                        ];

                        // Explicitly remove any existing conflicting keys that might have been set by base factory
                        unset(
                            $rootProperties[$propertyName]['type'],
                            $rootProperties[$propertyName]['properties'],
                            $rootProperties[$propertyName]['required'],
                            $rootProperties[$propertyName]['additionalProperties']
                        );
                    } else {
                        // For regular object schemas, include all properties
                        $rootProperties[$propertyName] = [
                            'type' => $jsonSchema['type'] ?? 'object',
                            'description' => $jsonSchema['description'] ?? '',
                            'properties' => $jsonSchema['properties'] ?? [],
                            'required' => $jsonSchema['required'] ?? [],
                            'additionalProperties' => $jsonSchema['additionalProperties'] ?? false
                        ];

                        // Handle anyOf schemas with type (edge case)
                        if (isset($jsonSchema['anyOf'])) {
                            $rootProperties[$propertyName]['anyOf'] = $jsonSchema['anyOf'];
                        }
                    }
                }
            }

            // Write back the modified properties
            $schema['properties'] = $rootProperties;
        }

        // Use ArrayObject access to modify definitions
        if ($definitions !== null) {
            $schema['definitions'] = $definitions;
        }

        return $schema;
    }
}
