# MatesOfMate 🤝

Community-driven extensions for [Symfony Mate](https://github.com/symfony/ai-mate) — the CLI that gives AI assistants superpowers for PHP development.

## What is this?

Symfony Mate is a local CLI that enhances AI assistants with knowledge about your PHP application. **MatesOfMate** is the community home for extensions that add framework-specific, CMS-specific, or domain-specific tools.

## Available Extensions

| Extension                          | Description                           | Status |
|------------------------------------|---------------------------------------|--------|
| [composer-extension](https://packagist.org/packages/matesofmate/composer-extension) | Composer dependency management tools with token-optimized output | ✅     |
| [phpstan-extension](https://packagist.org/packages/matesofmate/phpstan-extension) | PHPStan static analysis tools with token-optimized output | ✅     |
| [phpunit-extension](https://packagist.org/packages/matesofmate/phpunit-extension) | PHPUnit testing tools and test introspection | ✅     |
| [rector-extension](https://packagist.org/packages/matesofmate/rector-extension) | Rector inspect, dry-run preview, and explicit apply workflows | ✅     |

*Want to add your extension? [Create an issue to request repository](https://github.com/matesofmate/.github/issues)!*

## Creating Your Own Extension

Check out our [extension-template](https://github.com/matesofmate/extension-template) to get started quickly.

Extensions can provide:
- **Tools** — callable functions for the AI (e.g., `sulu-content-types`, `doctrine-schema`)
- **Resources** — static context about your framework/CMS, addressed by URI

## Quick Start

```bash
# Install an extension
composer require --dev matesofmate/example-extension

# Initialize Mate in the project
vendor/bin/mate init

# Refresh discovery artifacts if needed
vendor/bin/mate discover

# Verify it's loaded
vendor/bin/mate debug:extensions
vendor/bin/mate debug:capabilities

# Call a tool
vendor/bin/mate tools:list
vendor/bin/mate tools:call example-hello --name=World
```

In current Mate setups, extension discovery is handled automatically after Composer install and update.

## Join the Community

- ⭐ [awesome-mate](https://github.com/matesofmate/awesome-mate) — curated resources for Mate
- 🐛 [Issues](https://github.com/matesofmate/.github/issues) — report bugs, request features
- 🤝 [Contributing](https://github.com/matesofmate/.github/blob/main/CONTRIBUTING.md) — help us grow

## Related Projects

- [symfony/ai-mate](https://github.com/symfony/ai-mate) — the core Mate CLI
- [symfony/ai](https://github.com/symfony/ai) — full Symfony AI component suite

---

*"Because every Mate needs Mates"*
