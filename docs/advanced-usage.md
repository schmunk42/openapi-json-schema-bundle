<!-- file generated with AI assistance: Claude Code - 2025-11-23 00:00:00 -->

# Advanced Usage

This document covers advanced scenarios for the schmunk42 OpenAPI JSON Schema Bundle.

## Understanding Schema Registry

### The Problem

When building applications that integrate with multiple external APIs, you often need to store different configuration structures for each API. For example:

- **Basecamp API** needs: `account_id`, `access_token`, `project_id`
- **GitHub API** needs: `token`, `repository`, `owner`
- **GitLab API** needs: `personal_access_token`, `project_id`, `base_url`

Each API has a unique configuration structure, but storing them in separate database columns or tables creates unnecessary complexity.

### The Solution: Schema Registry

The Schema Registry pattern solves this by:

1. **Collecting multiple schemas** from different providers (one per API integration)
2. **Combining them** into a unified schema using JSON Schema's `anyOf` composition
3. **Allowing a single entity field** to accept any of the registered schema structures
4. **Validating** that data matches at least one of the available schemas

### Real-World Example

In the ZA7 project, an `ApiConfiguration` entity needs to store configurations for Basecamp, GitHub, and GitLab APIs. Instead of:

```php
// ❌ Bad: Multiple columns for different APIs
class ApiConfiguration {
    private ?array $basecampConfig;
    private ?array $githubConfig;
    private ?array $gitlabConfig;
}
```

We use a single `config` field that can accept any valid API configuration:

```php
// ✅ Good: Single field with multiple valid schemas
class ApiConfiguration {
    #[ORM\Column(type: 'json')]
    #[JsonSchema]  // Accepts Basecamp OR GitHub OR GitLab structure
    private array $config;
}
```

The Schema Registry ensures:
- The OpenAPI documentation shows all possible config structures
- Data is validated against the appropriate schema
- Clients know exactly what structure each API expects

## Creating Schema Providers

Schema providers allow you to register external JSON schemas that will be combined into unified schemas. Let's create providers for three different API integrations.

### 1. Implement SchemaProviderInterface

Create a provider for each API integration:

**BasecampExtension.php**
```php
namespace App\Extension;

use Schmunk42\OpenApiJsonSchema\Interface\SchemaProviderInterface;

class BasecampExtension implements SchemaProviderInterface
{
    public function getName(): string
    {
        return 'basecamp';
    }

    public function getSchemaPath(): string
    {
        return __DIR__ . '/../../config/schemas/api-basecamp.json';
    }
}
```

**GitHubExtension.php**
```php
namespace App\Extension;

use Schmunk42\OpenApiJsonSchema\Interface\SchemaProviderInterface;

class GitHubExtension implements SchemaProviderInterface
{
    public function getName(): string
    {
        return 'github';
    }

    public function getSchemaPath(): string
    {
        return __DIR__ . '/../../config/schemas/api-github.json';
    }
}
```

**GitLabExtension.php**
```php
namespace App\Extension;

use Schmunk42\OpenApiJsonSchema\Interface\SchemaProviderInterface;

class GitLabExtension implements SchemaProviderInterface
{
    public function getName(): string
    {
        return 'gitlab';
    }

    public function getSchemaPath(): string
    {
        return __DIR__ . '/../../config/schemas/api-gitlab.json';
    }
}
```

Each provider points to its own JSON schema file defining the structure for that specific API.

### 2. Create JSON Schema Files

Create schema files for each API:

**config/schemas/api-basecamp.json**
```json
{
    "$schema": "https://json-schema.org/draft-07/schema#",
    "type": "object",
    "properties": {
        "type": { "const": "basecamp" },
        "account_id": { "type": "string" },
        "access_token": { "type": "string" },
        "project_id": { "type": "string" }
    },
    "required": ["type", "account_id", "access_token"]
}
```

**config/schemas/api-github.json**
```json
{
    "$schema": "https://json-schema.org/draft-07/schema#",
    "type": "object",
    "properties": {
        "type": { "const": "github" },
        "token": { "type": "string" },
        "repository": { "type": "string" },
        "owner": { "type": "string" }
    },
    "required": ["type", "token", "repository", "owner"]
}
```

**config/schemas/api-gitlab.json**
```json
{
    "$schema": "https://json-schema.org/draft-07/schema#",
    "type": "object",
    "properties": {
        "type": { "const": "gitlab" },
        "personal_access_token": { "type": "string" },
        "project_id": { "type": "integer" },
        "base_url": { "type": "string", "format": "uri" }
    },
    "required": ["type", "personal_access_token", "project_id"]
}
```

Note the `type` field with `const` - this helps distinguish which schema a config object follows.

### 3. Register as Services

In `config/services.yaml`:

```yaml
services:
    # Auto-register all schema providers
    _instanceof:
        Schmunk42\OpenApiJsonSchema\Interface\SchemaProviderInterface:
            tags: ['schmunk42_open_api_json_schema.provider']

    # Register each provider
    App\Extension\BasecampExtension: ~
    App\Extension\GitHubExtension: ~
    App\Extension\GitLabExtension: ~
```

The bundle automatically discovers and registers all three providers via service tagging.

### 4. Access the Unified Schema

The `SchemaRegistry` combines all registered providers into a unified schema:

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

### How Multiple Schemas are Combined

The unified schema uses JSON Schema's `anyOf` to combine all providers. With our three providers (Basecamp, GitHub, GitLab), the registry produces:

```json
{
    "$schema": "https://json-schema.org/draft-07/schema#",
    "description": "Unified schema from 3 provider(s). Must match one of the available schemas.",
    "anyOf": [
        {
            "type": "object",
            "properties": {
                "type": { "const": "basecamp" },
                "account_id": { "type": "string" },
                "access_token": { "type": "string" },
                "project_id": { "type": "string" }
            },
            "required": ["type", "account_id", "access_token"]
        },
        {
            "type": "object",
            "properties": {
                "type": { "const": "github" },
                "token": { "type": "string" },
                "repository": { "type": "string" },
                "owner": { "type": "string" }
            },
            "required": ["type", "token", "repository", "owner"]
        },
        {
            "type": "object",
            "properties": {
                "type": { "const": "gitlab" },
                "personal_access_token": { "type": "string" },
                "project_id": { "type": "integer" },
                "base_url": { "type": "string", "format": "uri" }
            },
            "required": ["type", "personal_access_token", "project_id"]
        }
    ]
}
```

**How `anyOf` works**: Data is valid if it matches **at least one** of the schemas. This means:
- `{"type": "basecamp", "account_id": "123", "access_token": "abc"}` ✅ Valid (matches Basecamp schema)
- `{"type": "github", "token": "xyz", "repository": "repo", "owner": "user"}` ✅ Valid (matches GitHub schema)
- `{"type": "unknown", "foo": "bar"}` ❌ Invalid (matches no schema)

## Complete Example: Entity Using Unified Schema

Now let's put it all together. The `ApiConfiguration` entity uses the unified schema to accept any of the registered API configurations:

```php
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Schmunk42\OpenApiJsonSchema\Attribute\JsonSchema;
use Schmunk42\OpenApiJsonSchema\Interface\JsonSchemaProviderInterface;
use Schmunk42\OpenApiJsonSchema\OpenApi\JsonFieldSchemaDecorator;

#[ApiResource]
#[ORM\Entity]
class ApiConfiguration implements JsonSchemaProviderInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'string')]
    private string $id;

    #[ORM\Column(type: 'string')]
    private string $name;

    #[ORM\Column(type: 'json')]
    #[JsonSchema]  // Uses getJsonSchema() to inject unified schema
    private array $config = [];

    public static function getJsonSchema(string $fieldName): ?array
    {
        if ($fieldName === 'config') {
            // Access the SchemaRegistry via the decorator
            $schemaRegistry = JsonFieldSchemaDecorator::getSchemaRegistry();
            if ($schemaRegistry === null) {
                return null;
            }

            // Return the unified schema with all registered providers
            $unifiedSchema = $schemaRegistry->getUnifiedSchema();
            return [
                'description' => 'API Configuration - must match one of: Basecamp, GitHub, or GitLab',
                'anyOf' => $unifiedSchema['anyOf']
            ];
        }
        return null;
    }
}
```

### What This Achieves

1. **Single Database Column**: The `config` field is a single JSON column that can store any API configuration
2. **Multiple Valid Structures**: Data can be Basecamp, GitHub, or GitLab format
3. **OpenAPI Documentation**: API docs automatically show all three possible structures
4. **Automatic Validation**: Invalid configurations are rejected

### Example API Requests

All of these are valid POST requests to create an `ApiConfiguration`:

```json
// Basecamp configuration
{
    "name": "My Basecamp API",
    "config": {
        "type": "basecamp",
        "account_id": "123456",
        "access_token": "secret_token"
    }
}
```

```json
// GitHub configuration
{
    "name": "My GitHub API",
    "config": {
        "type": "github",
        "token": "ghp_abc123",
        "repository": "my-repo",
        "owner": "mycompany"
    }
}
```

```json
// GitLab configuration
{
    "name": "My GitLab API",
    "config": {
        "type": "gitlab",
        "personal_access_token": "glpat-xyz",
        "project_id": 42,
        "base_url": "https://gitlab.company.com"
    }
}
```

Each request stores the `config` in the same database column, but validates against the appropriate schema based on the `type` field.

## Dynamic Schemas with Entity Providers

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

## Schema Validation

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

## Cache Management

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
