# NGS Framework Modules Management

## Overview

NGS Framework supports modularization, enabling modules to function as independent sub-projects within the main application. Modules are accessible through distinct routing methods:

* **Domain:** Separate, dedicated domains.
* **Subdomain:** Specific subdomains.
* **URL Path:** Paths such as `domain/admin` redirecting requests to the respective module.

## Resolution Priority

When resolving which module should handle a request, the resolver applies the following priority order:

1. Domain mapping (default.domain.map) — highest priority.
2. Subdomain mapping (default.subdomain).
3. URL Path mapping (default.path) — lowest priority.

This ensures that dedicated domains always take precedence over subdomain or path-based routing.

## Modules Configuration

Modules are centrally configured via:

```
project_root/conf/modules.json
```

### Structure Example

```json
{
  "default": {
    "subdomain": {
      "admin": {"dir": "admin"},
      "data": {"dir": "admin"}
    },
    "path": {
      "ngs-AdminTools": {"dir": "ngs-AdminTools"},
      "ngs": {"dir": "ngs"}
    },
    "domain": {
      "map": "admin"
    },
    "default": {"dir": "NgsBi"}
  }
}
```

Modules can be defined as:

* **Project Modules:** Directories within the project's local `modules` folder.
* **Composer Modules:** Identified explicitly with Composer package names, distinguishing them from project modules.

## Standard Module Structure

Each module mirrors the main project's directory structure:

* `classes`
* `htdocs`
* `web`
* `templates`
* `data`
* `conf`

### Configuration Files

Modules must include environment-specific files:

* `constants.json`: Immutable values and configurations.
* `routes.json`: Routing information.

## Constants and Configurations

### Constants

* Immutable application-level values (version numbers, directory paths, class names).
* Allow module-specific overrides, supporting integration flexibility, especially with third-party Composer modules.
* Dynamically instantiate application components such as templaters and routing managers.

### Configurations

* Environment-specific settings managed by system administrators or DevOps.
* Database credentials and other settings unique to deployment environments.
* Allow runtime overriding of module constants through these configurations.

## NgsModule Class

Responsible for:

* Loading and managing configuration and constants from JSON.
* Determining runtime environment (development, staging, production).
* Providing methods to access and manage module-specific constants and configurations.

### Core Methods

* `getDefinedValue(string $key, ?string $module = null): mixed`
* `get(string $key, ?string $module = null): mixed`
* `define(string $key, mixed $value): void`
* `defined(string $key): bool`
* `getConfig(?string $prefix = null): mixed`
* `getModuleDirByNS(string $ns = ''): string`
* `getModuleByNS(string $ns = ''): string`
* `createDefinedInstance(string $constantName, string $expectedClass, bool $forceNew = false): object`

## Composer Modules Integration

Composer modules must be explicitly defined and distinguished within `modules.json`. The framework:

* Identifies Composer modules using Composer's `InstalledVersions`.
* Dynamically resolves module paths for initialization.

## Overriding Constants

Module-specific constants can be overridden within `modules.json` by:

* Explicit direct values.
* Environment-specific values from `config.json`.

This enables tailored module behavior across deployment contexts.

## Unified Module Retrieval

The unified method for retrieving modules:

```php
public function getModule(string $moduleName): NgsModule
```

* `$moduleName`: Module's directory or Composer package name.

This method:

* Loads and parses module configuration.
* Resolves and returns the appropriate module path.
* Instantiates the module object, verifying Composer package availability when necessary.

---

This document outlines the management logic for modules in the NGS Framework. For specific use cases and extended documentation, consult further project-specific guidelines.
