# Environment-Specific Constants in constants.json

This document explains how to specify environment-specific constants in the `constants.json` file.

## Overview

The NGS framework now supports environment-specific constants in the `constants.json` file. This allows you to define different values for constants based on the environment (production, development, staging, etc.).

## How It Works

When a constant in `constants.json` is defined as an object with environment names as keys, the framework will use the value corresponding to the current environment. If no environment-specific value is found, the default value will be used.

## Example

Here's an example of how to define environment-specific constants in `constants.json`:

```json
{
  "constants": {
    "ENVIRONMENT": {
      "production": "production",
      "development": "development",
      "staging": "staging"
    },
    "JS_BUILD_MODE": {
      "production": "production",
      "development": "development",
      "staging": "staging"
    },
    "LESS_BUILD_MODE": {
      "production": "production",
      "development": "development",
      "staging": "staging"
    },
    "SASS_BUILD_MODE": {
      "production": "production",
      "development": "development",
      "staging": "staging"
    },
    "REGULAR_CONSTANT": "value"
  }
}
```

In this example:
- If the environment is "production", `ENVIRONMENT` will be set to "production"
- If the environment is "development", `ENVIRONMENT` will be set to "development"
- If the environment is "staging", `ENVIRONMENT` will be set to "staging"
- `REGULAR_CONSTANT` will always be set to "value" regardless of the environment

## Supported Environments

The framework supports the following environments:
- production (default)
- development
- staging

You can specify values for any of these environments, or add your own custom environments.

## Implementation Details

The environment-specific constants are processed in the `loadConstants` method of the `NGSModule` class. When a constant value is an array and has a key matching the current environment, the value for that environment is used. Otherwise, the default value is used.