Here is a **combined and streamlined Markdown documentation** for the Naghashyan Framework routing system, merging both your general routing explanation and the detailed JSON route rules.
I ensured the rules and behavior described in your second doc take precedence where there are overlaps or ambiguity.

---

````markdown
# Routing Documentation for Naghashyan Framework

## Overview

The Naghashyan Framework routing system is organized as follows:

- **Module**: Represents a subproject or functional area, identified by:
    - Domain
    - Subdomain
    - Or the first segment of the URL (for path-based modules)

- **Package**: The first URL segment after the module (or the first segment in non-path modules). Packages are namespaces or directories containing `NgsRequest` instances (such as Loads and Actions). Each package corresponds to a `routes.json` configuration in `config/routes`.

- **Request Identification**: After identifying the module and package, the next URL segment matches a key in the package's `routes.json` file. Additional segments become request parameters (arguments).

---

## Routing Behavior

1. **URL Matching**
    - If the request segment matches a route key in the loaded routes file, the corresponding request is loaded.
    - If the segment is missing, the `default` key route is loaded.
    - If the segment does not match any key, a 404 route is loaded as defined in the corresponding `404` file.

2. **URL Arguments**
    - Additional segments after identifying the request are treated as parameters and are available as the `args` array.

---

## Route Definition Structure

Each route is defined as a JSON object with these fields:

| Field         | Description                                                        |
|---------------|--------------------------------------------------------------------|
| `route`       | URL segment(s) or pattern to match.                                |
| `action`      | Main load/action triggered for this route.                         |
| `nestedLoad`  | (Optional) Additional actions to be loaded as nested components.   |
| `constraints` | (Optional) Regex rules for dynamic URL segments (parameters).      |

### Example `routes.json`:

```json
[
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
````

---

## Route Pattern Rules

### 1. **Exact Matching**

A simple route like `"overview"` matches exactly that segment.

* `/account/overview` → `loads.account.main.main` + nested `loads.account.main.overview`

### 2. **Parameterized Routes with Constraints**

Define parameters with `[:]` and restrict with regex in `constraints`.
If the value **does not match** the regex, a 404 is returned.

* Route: `"update-email/[:code]"`
* Constraints: `"code": "[A-Za-z0-9]+"`
* `/account/update-email/123` → `loads.main.change_email` + nested actions
* `/account/update-email/` or `/account/update-email/!!!` → **404**

### 3. **Optional Parameters**

Use `[/:param]` for optional params; value (if present) must match the constraint.

* Route: `"update-email[/:code]"`
* Constraints: `"code": "[A-Za-z0-9]+"`
* `/account/update-email` or `/account/update-email/123` → matches
* `/account/update-email/!!!` → **404**

### 4. **Multiple Parameters**

You can define several params with their own constraints.

```json
{
  "route": "user/:id/order/:orderId",
  "constraints": {
    "id": "\\d+",
    "orderId": "[A-Za-z0-9]+"
  }
}
```

* `/user/45/order/abc123` → matches

### 5. **Constraints**

* Only **regular expressions** are supported.
* If any param does not match, the route is skipped and 404 returned.

### 6. **Nested Loads**

* `nestedLoad` allows multiple additional loads (views/components) to be loaded for the same request.
* Each key under `nestedLoad` represents a component/namespace.

---

## Action Notation

* `action` fields use dot notation (`loads.account.user.auth.account_code_verification`)
* This maps to a PHP class: `loads/account/user/auth/AccountCodeVerificationLoad.php`

---

## URL Parsing Visualization

### Default Module Examples

| URL                                    | Module  | Package         | Request                                  | Arguments (`args`)                  |
| -------------------------------------- | ------- | --------------- | ---------------------------------------- | ----------------------------------- |
| `/account`                             | default | account         | default (`loads.account.main.main`)      | `[]`                                |
| `/account/overview`                    | default | account         | overview (`loads.account.main.overview`) | `[]`                                |
| `/account/edit`                        | default | account         | edit (`loads.account.main.edit_profile`) | `[]`                                |
| `/account/update-email/ABC123`         | default | account         | update-email (`loads.main.change_email`) | `[code: "ABC123"]`                  |
| `/account/static/terms-and-conditions` | default | account         | static (`loads.account.static.static`)   | `[urlHash: "terms-and-conditions"]` |
| `/unknown-segment`                     | default | unknown-segment | 404 load                                 | `[]`                                |
| `/account/unknown-action`              | default | account         | 404 load                                 | `[]`                                |

### PATH Module Examples

For modules declared as type `PATH`, the first segment identifies the module:

| URL                                    | Module | Package | Request                                  | Arguments (`args`)            |
| -------------------------------------- | ------ | ------- | ---------------------------------------- | ----------------------------- |
| `/user/account`                        | user   | account | default (`loads.account.main.main`)      | `[]`                          |
| `/user/account/overview`               | user   | account | overview (`loads.account.main.overview`) | `[]`                          |
| `/admin/account/edit`                  | admin  | account | edit (`loads.account.main.edit_profile`) | `[]`                          |
| `/admin/account/update-email/ABC123`   | admin  | account | update-email (`loads.main.change_email`) | `[code: "ABC123"]`            |
| `/admin/account/static/privacy-policy` | admin  | account | static (`loads.account.static.static`)   | `[urlHash: "privacy-policy"]` |

---

## Summary Table

| Route Syntax                | Constraints                        | Example URL                                    | Notes                         |
| --------------------------- | ---------------------------------- | ---------------------------------------------- | ----------------------------- |
| `"overview"`                | none                               | `/account/overview/gago`                       | Exact match                   |
| `"update-email/[:code]"`    | code: `[A-Za-z0-9]+`               | `/account/update-email/123`                    | Param required, regex-checked |
| `"update-email[/:code]"`    | code: `[A-Za-z0-9]+`               | `/account/update-email` OR `/update-email/123` | Param optional, regex-checked |
| `"user/:id/order/:orderId"` | id: `\d+`, orderId: `[A-Za-z0-9]+` | `/user/45/order/abc123`                        | Multiple params               |

---

## Important Notes

* All routes are checked in the order they are defined.
* If none match, or constraints fail, a **404** is returned.
* The corresponding PHP Load class must exist in the indicated namespace.
* Nested loads are resolved recursively, enabling modular and composable view/component loading.

---

## Example Usage

* `/account` triggers `loads.account.main.main`.
* `/account/overview` triggers `loads.account.main.main` with nested load `loads.account.main.overview`.
* `/account/update-email/ABC123` matches `update-email/[:code]`, where `ABC123` is passed as argument `code`.
* If a parameter fails the regex constraint, the route is **not matched** and a 404 is returned.

```

---

**If you need this as a downloadable `.md` file or want to integrate with a specific documentation tool (like Docusaurus or GitBook), let me know!**
```
