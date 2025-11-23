# Changelog

All notable changes to the Schmunk42 OpenAPI JSON Schema Bundle will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial release of schmunk42/openapi-json-schema-bundle
- `SchemaRegistry` service for aggregating JSON schemas from multiple providers
- `JsonFieldSchemaDecorator` for integrating JSON schemas into API Platform OpenAPI documentation
- `SchemaProviderInterface` for registering external schema sources
- `JsonSchemaProviderInterface` for entity-level dynamic schema generation
- `#[JsonSchema]` PHP attribute for marking JSON fields
- Auto-discovery of schema providers via Symfony service tagging
- Caching support for unified schemas with configurable TTL
- Support for JSON Schema `anyOf` composition pattern
- Configuration options: `schema_base_path` and `cache_ttl`

### Changed
- Refactored from ZA7 application code into standalone bundle
- Renamed `SchemaManager` to `SchemaRegistry` for better semantic clarity
- Replaced static `ApiExtensionRegistry` dependency with tagged iterator pattern
- Made schema base path configurable (was hardcoded)

### Extracted from ZA7
The following components were extracted and refactored from the ZA7 project:
- `App\Attribute\JsonSchema` → `Schmunk42\OpenApiJsonSchema\Attribute\JsonSchema`
- `App\Interface\JsonSchemaProviderInterface` → `Schmunk42\OpenApiJsonSchema\Interface\JsonSchemaProviderInterface`
- `App\OpenApi\JsonFieldSchemaDecorator` → `Schmunk42\OpenApiJsonSchema\OpenApi\JsonFieldSchemaDecorator`
- `App\Service\SchemaManager` → `Schmunk42\OpenApiJsonSchema\Service\SchemaRegistry`

## [1.0.0] - 2025-11-22

### Initial Bundle Creation
- Extracted JSON Schema infrastructure from ZA7 application
- Created as internal bundle in `extensions/openapi-json-schema-bundle/`
- Fully tested with ZA7's existing test suite (26 tests passing)
- Supports 4 schema providers: Basecamp 2, GitHub, GitLab, Jira XML

---

Generated with AI assistance: Claude Code - 2025-11-22
