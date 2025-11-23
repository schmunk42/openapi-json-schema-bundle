# schmunk42 OpenAPI JSON Schema Bundle

A Symfony bundle that integrates JSON Schema validation with API Platform's OpenAPI documentation. This bundle allows you to dynamically populate OpenAPI schemas for JSON fields by aggregating schemas from multiple providers.

## Features

- **Dynamic JSON Schema Integration**: Automatically inject JSON schemas into API Platform OpenAPI documentation
- **Multiple Schema Providers**: Aggregate schemas from multiple sources using a registry pattern
- **anyOf Schema Composition**: Combine multiple schemas into a unified schema with JSON Schema's `anyOf`
- **PHP 8 Attributes**: Use simple `#[JsonSchema]` attribute to mark JSON fields for schema integration
- **Entity-Level Schema Control**: Entities can provide dynamic schemas via `JsonSchemaProviderInterface`
- **Auto-Discovery**: Schema providers are automatically registered via Symfony's service tagging
- **Caching**: Built-in caching for unified schemas to improve performance
- **Configurable**: Schema base path and cache TTL are configurable

## Installation

### 1. Add to composer.json

Since this is an internal bundle in the `extensions/` directory, add autoloading in your `composer.json`:

```json
{
    "autoload": {
        "psr-4": {
            "Schmunk42\\OpenApiJsonSchema\\": "extensions/openapi-json-schema-bundle/src/"
        }
    }
}
```

Run composer dump-autoload:

```bash
composer dump-autoload
```

### 2. Register Bundle

Add the bundle to `config/bundles.php`:

```php
return [
    // ...
    Schmunk42\OpenApiJsonSchema\Schmunk42OpenApiJsonSchemaBundle::class => ['all' => true],
];
```

### 3. Configure (Optional)

Create `config/packages/schmunk42_open_api_json_schema.yaml`:

```yaml
schmunk42_open_api_json_schema:
    schema_base_path: '%kernel.project_dir%/config/schemas'  # Default
    cache_ttl: 3600  # Cache TTL in seconds (default: 1 hour)
```

## Usage

### Basic Usage with Attributes

Use the `#[JsonSchema]` attribute to mark JSON fields that should have their schema injected into OpenAPI:

```php
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Schmunk42\OpenApiJsonSchema\Attribute\JsonSchema;

#[ApiResource]
#[ORM\Entity]
class MyEntity
{
    #[ORM\Column(type: 'json')]
    #[JsonSchema('my-schema.json')]
    private array $config = [];
}
```

The bundle will automatically load `my-schema.json` from your configured `schema_base_path` and inject it into the OpenAPI documentation for this field.

### Dynamic Schemas with Entity Providers

For more complex scenarios where the schema depends on runtime logic, implement `JsonSchemaProviderInterface`:

```php
use Schmunk42\OpenApiJsonSchema\Interface\JsonSchemaProviderInterface;

#[ApiResource]
#[ORM\Entity]
class MyEntity implements JsonSchemaProviderInterface
{
    #[ORM\Column(type: 'json')]
    #[JsonSchema]  // No path needed - uses getJsonSchema()
    private array $config = [];

    public static function getJsonSchema(string $fieldName): ?array
    {
        if ($fieldName === 'config') {
            return [
                'type' => 'object',
                'properties' => [
                    'api_key' => ['type' => 'string'],
                    'timeout' => ['type' => 'integer']
                ],
                'required' => ['api_key']
            ];
        }
        return null;
    }
}
```

### Creating Schema Providers

Schema providers allow you to register external JSON schemas that will be combined into unified schemas.

#### 1. Implement SchemaProviderInterface

```php
namespace App\Extension;

use Schmunk42\OpenApiJsonSchema\Interface\SchemaProviderInterface;

class MyApiExtension implements SchemaProviderInterface
{
    public function getName(): string
    {
        return 'my_api';  // Unique identifier
    }

    public function getSchemaPath(): string
    {
        return __DIR__ . '/../config/my-api-schema.json';
    }
}
```

#### 2. Register as Service

In `config/services.yaml`:

```yaml
services:
    # Auto-register schema providers
    _instanceof:
        Schmunk42\OpenApiJsonSchema\Interface\SchemaProviderInterface:
            tags: ['schmunk42_open_api_json_schema.provider']

    App\Extension\MyApiExtension: ~
```

The bundle will automatically discover and register this provider via service tagging.

#### 3. Use Unified Schema

Access the unified schema combining all providers:

```php
use Schmunk42\OpenApiJsonSchema\Service\SchemaRegistry;

class MyService
{
    public function __construct(
        private readonly SchemaRegistry $schemaRegistry
    ) {}

    public function getUnifiedSchema(): array
    {
        return $this->schemaRegistry->getUnifiedSchema();
    }
}
```

The unified schema will have this structure:

```json
{
    "$schema": "https://json-schema.org/draft-07/schema#",
    "description": "Unified schema from N provider(s). Must match one of the available schemas.",
    "anyOf": [
        { "...": "schema from provider 1" },
        { "...": "schema from provider 2" },
        { "...": "schema from provider N" }
    ]
}
```

## Advanced Usage

### Using Unified Schema in Entity

You can combine static schema providers with entity-level dynamic schemas:

```php
use Schmunk42\OpenApiJsonSchema\Interface\JsonSchemaProviderInterface;
use Schmunk42\OpenApiJsonSchema\OpenApi\JsonFieldSchemaDecorator;

#[ApiResource]
class ApiConfiguration implements JsonSchemaProviderInterface
{
    #[ORM\Column(type: 'json')]
    #[JsonSchema]
    private array $config = [];

    public static function getJsonSchema(string $fieldName): ?array
    {
        if ($fieldName === 'config') {
            // Access the SchemaRegistry via the decorator
            $schemaRegistry = JsonFieldSchemaDecorator::getSchemaRegistry();
            if ($schemaRegistry === null) {
                return null;
            }

            $unifiedSchema = $schemaRegistry->getUnifiedSchema();
            return [
                'description' => $unifiedSchema['description'] ?? 'API Configuration',
                'anyOf' => $unifiedSchema['anyOf']
            ];
        }
        return null;
    }
}
```

### Schema Validation

Use `SchemaRegistry` to validate data against unified schemas:

```php
use Schmunk42\OpenApiJsonSchema\Service\SchemaRegistry;
use Opis\JsonSchema\Validator;

class MyValidator
{
    public function __construct(
        private readonly SchemaRegistry $schemaRegistry
    ) {}

    public function validate(array $data): bool
    {
        $validator = new Validator();
        $schema = json_decode(json_encode($this->schemaRegistry->getUnifiedSchema()));
        $dataObject = json_decode(json_encode($data));

        $result = $validator->validate($dataObject, $schema);
        return $result->isValid();
    }
}
```

### Cache Management

The bundle caches unified schemas for performance. To invalidate cache:

```php
use Schmunk42\OpenApiJsonSchema\Service\SchemaRegistry;

class MyService
{
    public function __construct(
        private readonly SchemaRegistry $schemaRegistry
    ) {}

    public function refreshSchemas(): void
    {
        $this->schemaRegistry->invalidateCache();
        // Next call to getUnifiedSchema() will rebuild from sources
    }
}
```

## Architecture

### Components

1. **SchemaRegistry** (`Service/SchemaRegistry.php`)
   - Central registry for schema providers
   - Aggregates schemas into unified `anyOf` structure
   - Handles caching

2. **JsonFieldSchemaDecorator** (`OpenApi/JsonFieldSchemaDecorator.php`)
   - Decorates API Platform's schema factory
   - Processes `#[JsonSchema]` attributes
   - Injects schemas into OpenAPI documentation

3. **Interfaces**
   - `SchemaProviderInterface`: For external schema sources
   - `JsonSchemaProviderInterface`: For entity-level dynamic schemas

4. **Attribute**
   - `#[JsonSchema]`: Marks fields for schema injection

### How It Works

1. **Service Registration**: Schema providers implementing `SchemaProviderInterface` are auto-tagged with `schmunk42_open_api_json_schema.provider`

2. **Registry Population**: `SchemaRegistry` receives all tagged providers via tagged iterator

3. **Schema Aggregation**: When `getUnifiedSchema()` is called, registry loads all provider schemas and combines them using `anyOf`

4. **OpenAPI Integration**: `JsonFieldSchemaDecorator` decorates API Platform's schema factory and processes entities during OpenAPI generation

5. **Attribute Processing**: Fields with `#[JsonSchema]` attribute get their schema from:
   - File path (if provided in attribute)
   - Entity's `getJsonSchema()` method (if implements `JsonSchemaProviderInterface`)
   - SchemaRegistry (if entity uses unified schema)

## Requirements

- PHP 8.1+
- Symfony 7.0+
- API Platform 3.0+
- opis/json-schema (for validation)

## License

Proprietary - herzog kommunikation GmbH

## Credits

Developed for the ZA7 (Zentrales Agentursystem Version 7) project.
