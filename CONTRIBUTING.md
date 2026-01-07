# Contributing to MatesOfMate

First off, thanks for taking the time to contribute! 🎉

## How Can I Contribute?

### Submitting a New Extension

We welcome extensions for any PHP framework, CMS, or tool! Here's how:

1. **Use the template**: Start from [extension-template](https://github.com/matesofmate/extension-template)
2. **Follow naming conventions**: `{framework}-extension` (e.g., `example-extension`, `api-platform-extension`)
3. **Include documentation**: A clear README with installation and usage examples
4. **Add tests**: At minimum, test that tools are discoverable

### Proposing an Extension Idea

Not ready to build it yourself? Open an [issue](https://github.com/matesofmate/.github/issues) with:
- The framework/CMS you'd like supported
- What tools would be useful
- Any relevant documentation links

### Improving Existing Extensions

- Bug fixes are always welcome
- New tools should be discussed in an issue first
- Documentation improvements need no prior discussion

## Development Setup

```bash
# Clone the extension you want to work on
git clone git@github.com:matesofmate/example-extension.git
cd example-extension

# Install dependencies
composer install

# Run tests
composer test

# Check code style and static analysis
composer lint

# Auto-fix code style and apply refactorings
composer fix
```

## Extension Guidelines

### Naming Conventions

- **Package name**: `matesofmate/{framework}-extension`
- **Namespace**: `MatesOfMate\{Framework}\`
- **Tool names**: `{framework}-{action}` (e.g., `sulu-content-types`, `sulu-webspaces`)

### Required Files

```
your-extension/
├── composer.json          # With extra.ai-mate config
├── README.md              # Installation & usage
├── LICENSE                # MIT recommended
├── src/
│   └── Capability/        # Your MCP tools
├── config/
│   └── services.php       # Service definitions
└── tests/
    └── ...
```

### composer.json Requirements

```json
{
    "name": "matesofmate/example-extension",
    "type": "library",
    "require": {
        "php": ">=8.2",
        "symfony/ai-mate": "^0.1"
    },
    "autoload": {
        "psr-4": {
            "MatesOfMate\\ExampleExtension\\": "src/"
        }
    },
    "extra": {
        "ai-mate": {
            "scan-dirs": ["src/Capability"],
            "includes": ["config/services.php"]
        }
    }
}
```

### Tool Best Practices

1. **Single responsibility**: One tool = one focused action
2. **Clear descriptions**: The AI reads these to decide when to use the tool
3. **Helpful output**: Return structured, actionable information
4. **Error handling**: Graceful failures with useful error messages

```php
<?php

namespace MatesOfMate\ExampleExtension\Capability;

use Mcp\Capability\Attribute\McpTool;

class ListThingsTool
{
    #[McpTool(
        name: 'example-list-things',
        description: 'Lists all configured things in the application. Use this when the user asks about available things or needs to reference thing names.'
    )]
    public function execute(): string
    {
        // Implementation
        return 'List of things';
    }
}
```

### Resource Best Practices

Resources provide static context or configuration data to the AI. They're useful for:
- Framework configuration
- Available routes, entities, or components
- System status and metadata

1. **Clear URIs**: Use descriptive custom URI schemes (e.g., `myframework://routes`)
2. **Structured data**: Return well-structured JSON or plain text
3. **Static content**: Resources should provide relatively stable information
4. **Helpful context**: Include data that helps the AI understand your system

```php
<?php

namespace MatesOfMate\ExampleExtension\Capability;

use Mcp\Capability\Attribute\McpResource;

class ConfigurationResource
{
    #[McpResource(
        uri: 'example://config',
        name: 'example_config',
        mimeType: 'application/json'
    )]
    public function getConfiguration(): array
    {
        return [
            'uri' => 'example://config',
            'mimeType' => 'application/json',
            'text' => json_encode([
                'version' => '1.0.0',
                'features' => ['feature_a' => true, 'feature_b' => false],
            ], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT),
        ];
    }
}
```

## Code Quality & Style

We use multiple tools to ensure code quality:

- **PHP CS Fixer**: PSR-12 and Symfony coding standards
- **PHPStan**: Static analysis at level 8
- **Rector**: Automated refactoring and modernization

```bash
# Check everything (code style + static analysis)
composer lint

# Auto-fix everything (refactoring + code style)
composer fix
```

The `lint` command runs:
1. Composer JSON validation
2. Rector in dry-run mode
3. PHP CS Fixer in dry-run mode
4. PHPStan static analysis (level 8)

The `fix` command runs:
1. Rector for automated refactorings
2. PHP CS Fixer to fix code style

## Pull Request Process

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-tool`)
3. Make your changes
4. Run tests and style checks
5. Commit with a clear message
6. Push and open a PR

### PR Checklist

- [ ] Tests pass (`composer test`)
- [ ] Code quality checks pass (`composer lint`)
  - [ ] PHP CS Fixer passes
  - [ ] PHPStan level 8 passes
- [ ] README updated (if needed)
- [ ] CHANGELOG updated

---

*Happy coding, mate!* 🚀
