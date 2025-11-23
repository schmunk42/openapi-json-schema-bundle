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

## Basic Usage

### Simple Example: Tags Array

Here's a simple example of adding JSON schema validation to a tags field:

```php
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Schmunk42\OpenApiJsonSchema\Attribute\JsonSchema;

#[ApiResource]
#[ORM\Entity]
class Article
{
    #[ORM\Id]
    #[ORM\Column(type: 'string')]
    private string $id;

    #[ORM\Column(type: 'string')]
    private string $title;

    #[ORM\Column(type: 'json')]
    #[JsonSchema('article-tags.json')]
    private array $tags = [];
}
```

Create `config/schemas/article-tags.json`:

```json
{
    "$schema": "https://json-schema.org/draft-07/schema#",
    "type": "array",
    "items": {
        "type": "string",
        "minLength": 1,
        "maxLength": 50
    },
    "uniqueItems": true,
    "maxItems": 10
}
```

### The Power of the Annotation

**Key Concept**: `#[ORM\Column(type: 'json')]` + `#[JsonSchema]` = Full JSON Schema in OpenAPI

The bundle automatically injects your JSON schema into the OpenAPI documentation. This means:
- Your database column stores validated JSON data
- Your API documentation shows the exact schema structure
- Clients can see what data structure is expected

**IMPORTANT**: Including schemas this way still produces **fully valid JSON schemas**, which are **fully compatible with OpenAPI 3.1**.

### OpenAPI Output

With the example above, your OpenAPI schema will include:

```json
{
  "Article": {
    "type": "object",
    "properties": {
      "id": {
        "type": "string"
      },
      "title": {
        "type": "string"
      },
      "tags": {
        "type": "array",
        "items": {
          "type": "string",
          "minLength": 1,
          "maxLength": 50
        },
        "uniqueItems": true,
        "maxItems": 10
      }
    }
  }
}
```

### Dynamic Schemas from Entity Method

For schemas that require runtime logic, use the `#[JsonSchema]` attribute without a path and implement `JsonSchemaProviderInterface`:

```php
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Schmunk42\OpenApiJsonSchema\Attribute\JsonSchema;
use Schmunk42\OpenApiJsonSchema\Interface\JsonSchemaProviderInterface;

#[ApiResource]
#[ORM\Entity]
class Report implements JsonSchemaProviderInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'string')]
    private string $id;

    #[ORM\Column(type: 'json')]
    #[JsonSchema]  // No path - uses getJsonSchema() method
    private array $filters = [];

    public static function getJsonSchema(string $fieldName): ?array
    {
        if ($fieldName === 'filters') {
            $firstDayOfMonth = (new \DateTime('first day of this month'))->format('Y-m-d');

            return [
                'type' => 'object',
                'properties' => [
                    'dateFrom' => [
                        'type' => 'string',
                        'format' => 'date',
                        'default' => $firstDayOfMonth,
                        'description' => 'Start date for report (defaults to first day of current month)'
                    ],
                    'dateTo' => [
                        'type' => 'string',
                        'format' => 'date',
                        'description' => 'End date for report'
                    ]
                ],
                'required' => ['dateFrom', 'dateTo']
            ];
        }
        return null;
    }
}
```

This approach is useful when:
- Schema needs runtime-calculated values (like dates)
- Schema structure depends on application state
- You want to keep schema logic close to the entity

## Advanced Usage

For complex scenarios including:
- Creating schema providers for unified schemas
- Schema validation
- Cache management
- Using unified schemas in entities

See [Advanced Usage Documentation](docs/advanced-usage.md)

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
