# Routing Documentation for Naghashyan Framework

## Overview

The Naghashyan Framework routing hierarchy is organized in the following manner:

* **Module**: Represents a separate subproject of the system. A module can be identified by:

    * Domain
    * Subdomain
    * URL segment (path)

* **Package**: The first segment in the URL (or the first after the module segment in the case of path modules). Packages are namespaces or directories containing `NgsRequest` instances (such as Loads and Actions). Each package corresponds to a `routes.json` configuration file located in the `config/routes` directory.

* **Request Identification**: After identifying the module and package, the subsequent URL segment is used to identify the specific request from the loaded routes configuration. This URL segment must match a key defined in the package's `routes.json` file.

## Routing Behavior

1. **Matching URL Segments:**

    * If the second segment of the URL matches a defined key in the loaded routes file, the corresponding request will be loaded.
    * If the segment is missing, the route defined under the `default` key will be loaded.

2. **Non-Matching Segments (404 Handling):**

    * If the URL segment does not match any defined key, the framework loads a 404 route as defined in the corresponding `404` routes file.

3. **URL Arguments:**

    * Any additional segments after identifying the request are treated as arguments and are stored in an array named `args`.

## Example Routes Configuration (`routes.json`):

```json
{
  "account": [
    {
      "route": "",
      "action": "loads.account.main.main"
    },
    {
      "route": "overview",
      "action": "loads.account.main.main",
      "nestedLoad": {
        "content": {
          "action": "loads.account.main.overview"
        }
      }
    },
    {
      "route": "edit",
      "action": "loads.account.main.main",
      "nestedLoad": {
        "content": {
          "action": "loads.account.main.edit_profile"
        }
      }
    },
    {
      "route": "update-email/[:code]",
      "constraints": {
        "code": "[A-Za-z0-9]+"
      },
      "nestedLoad": {
        "content": {
          "action": "loads.main.change_email"
        },
        "im_dialogContainer": {
          "action": "loads.account.user.auth.account_code_verification"
        }
      }
    },
    {
      "route": "static/[:urlHash]",
      "constraints": {
        "urlHash": "[A-Za-z0-9_/-]+"
      },
      "action": "loads.account.main.main",
      "nestedLoad": {
        "content": {
          "action": "loads.account.static.static"
        }
      }
    }
  ]
}
```

### Explanation of JSON Fields:

* **route**: Defines the URL segment(s) that must match the incoming URL.
* **action**: Specifies the main load/action that is triggered when the route matches.
* **nestedLoad**: Allows loading of additional nested components or views within the main action.
* **constraints**: Defines regex constraints for dynamic segments in the URL (e.g., `[:code]`).

## URL Parsing Visualization and Examples

Here's how different URLs are parsed into segments:

### Default Module Examples

| URL                                    | Module  | Package         | Request                                  | Arguments (`args`)                 |
| -------------------------------------- | ------- | --------------- | ---------------------------------------- | ---------------------------------- |
| `/account`                             | default | account         | default (`loads.account.main.main`)      | \[]                                |
| `/account/overview`                    | default | account         | overview (`loads.account.main.overview`) | \[]                                |
| `/account/edit`                        | default | account         | edit (`loads.account.main.edit_profile`) | \[]                                |
| `/account/update-email/ABC123`         | default | account         | update-email (`loads.main.change_email`) | \[code: "ABC123"]                  |
| `/account/static/terms-and-conditions` | default | account         | static (`loads.account.static.static`)   | \[urlHash: "terms-and-conditions"] |
| `/unknown-segment`                     | default | unknown-segment | 404 load                                 | \[]                                |
| `/account/unknown-action`              | default | account         | 404 load                                 | \[]                                |

### PATH Module Examples

When the module type is `PATH`, the first segment identifies the module:

| URL                                    | Module | Package | Request                                  | Arguments (`args`)           |
| -------------------------------------- | ------ | ------- | ---------------------------------------- | ---------------------------- |
| `/user/account`                        | user   | account | default (`loads.account.main.main`)      | \[]                          |
| `/user/account/overview`               | user   | account | overview (`loads.account.main.overview`) | \[]                          |
| `/admin/account/edit`                  | admin  | account | edit (`loads.account.main.edit_profile`) | \[]                          |
| `/admin/account/update-email/ABC123`   | admin  | account | update-email (`loads.main.change_email`) | \[code: "ABC123"]            |
| `/admin/account/static/privacy-policy` | admin  | account | static (`loads.account.static.static`)   | \[urlHash: "privacy-policy"] |

### Example Usage:

* `/account` will trigger `loads.account.main.main`.
* `/account/overview` will trigger `loads.account.main.main` with nested load `loads.account.main.overview`.
* `/account/update-email/ABC123` matches `update-email/[:code]`, where `ABC123` is passed as an argument named `code`.
