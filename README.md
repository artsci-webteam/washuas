# WashU A&S (washuas)

A shared custom Drupal module — and a set of submodules — maintained by the
Arts & Sciences web team at Washington University in St. Louis. It bundles the
helper functions, hooks, services, and editor/integration features that our
Drupal sites have in common so they can be developed once and pulled into every
project via Composer.

Because it is distributed as a Composer package (`washuas/washuas`), the same
versioned code is shared across repos rather than copy‑pasted into each one.

## Where it's used

`washuas` is required by most of the A&S Drupal projects, including:

- [deps](https://github.com/artsci-webteam/deps)
- [artsci](https://github.com/artsci-webteam/artsci)
- [prehealth](https://github.com/artsci-webteam/prehealth)
- [researchbase](https://github.com/artsci-webteam/researchbase)
- [drupalhdw](https://github.com/artsci-webteam/drupalhdw)

Each project pins the version of `washuas` it wants in its own `composer.json`
(see [Pulling updates into the other repos](#pulling-updates-into-the-other-repos)).

## Modules

The package ships a top‑level module plus several optional submodules. A project
enables only the ones it needs.

| Module | Machine name | What it does |
|--------|--------------|--------------|
| **WashU A&S** | `washuas` | The base module. Shared hooks and helpers — a `rule year` allowed‑values list, a `hook_views_query_alter()` that hides nodes flagged `private` from users without the `access private content` permission, the `WashU A&S Module` admin config menu (`/admin/config/washuas`), and the `washuas.entitytools` (`EntityTools`) service for loading and updating nodes by field values. |
| **SSO Assign Roles at Login** | `sso_assign_roles_at_login` | Automatically assigns Drupal roles to specified email addresses when those users authenticate via SSO (SimpleSAMLphp). Role/email lists are managed in config. See the [submodule README](modules/sso_assign_roles_at_login/README.md). |
| **WashU A&S Anchor Link** | `washuas_anchor_link` | WashU fork of the CKEditor Anchor Link contrib module, updated for CKEditor 5. |
| **WashU A&S Courses** | `washuas_courses` | Imports course information from the Courses web service (MuleSoft/Workday). Includes cron‑based imports, a manual import form, API/academic‑unit settings, and exposed‑filter handling for the course `semester` filter. |
| **WashU A&S WUCrsL** | `washuas_wucrsl` | Imports course information from the legacy WUCrsL SOAP web service. The WUCrsL counterpart to `washuas_courses`. |
| **WashU A&S iFrame** | `washuas_iframe` | Adds WashU‑tweaked CKEditor 5 iframe / HTML‑embed functionality. |

## SimpleSAMLphp & Single Sign‑On

The base module requires `drupal/simplesamlphp_auth`, which keeps the SSO module
version consistent across every installation. The package also includes
`scripts/symlink-simplesaml-config.sh`, which symlinks the SimpleSAMLphp `cert`,
`config`, and `metadata` directories from
`vendor/simplesamlphp/simplesamlphp/` to the shared
`exports/nfsdrupal/all_drupal/config/simplesamlphp/` location so that every
Drupal install uses the same SimpleSAMLphp configuration.

> **Note:** This script runs automatically via Composer's `post-install-cmd` /
> `post-update-cmd` scripts (see [Installation](#installation)) — but **only**
> when the consuming project wires those scripts up. Composer does not run a
> dependency's scripts on its own.

## Installation

To add `washuas` to a project for the first time:

**1. Register the repository** in the root `composer.json` under `repositories`:

```json
"washuas/washuas": {
  "url": "https://github.com/artsci-webteam/washuas.git",
  "type": "git"
}
```

**2. Require the module:**

```bash
composer require washuas/washuas
```

**3. Wire up the Composer scripts** so the SimpleSAMLphp symlink script runs on
install/update. Add the following to the `scripts` section of the root
`composer.json`:

```json
"post-install-cmd": [
  "@composer drupal:scaffold",
  "composer run-script post-install-cmd -d web/modules/custom/washuas"
],
"post-update-cmd": [
  "@composer drupal:scaffold",
  "composer run-script post-install-cmd -d web/modules/custom/washuas"
]
```

> **Note:** Composer does **not** automatically run scripts from dependencies.
> See [composer/composer#1193](https://github.com/composer/composer/issues/1193)
> for details.

**4. Enable the module(s) you need:**

```bash
drush en washuas washuas_courses -y
```

## Updating washuas (releasing a new version)

`washuas` is versioned with git tags (e.g. `v1.2.9`). Consuming projects pin a
tag, so a change isn't "live" anywhere until you cut a new release.

1. Branch off `main`, make your changes, and open a pull request against
   [artsci-webteam/washuas](https://github.com/artsci-webteam/washuas).
2. Once the PR is reviewed and merged into `main`, tag a new release. Follow
   [semantic versioning](https://semver.org/) — bump the patch for fixes, the
   minor for new functionality:

   ```bash
   git checkout main
   git pull
   git tag v1.2.10
   git push origin v1.2.10
   ```

3. Create a matching [GitHub release](https://github.com/artsci-webteam/washuas/releases)
   for the tag with a short summary of what changed.

> **Note:** Don't reuse or move an existing tag — Composer caches versions by
> tag, so always cut a new version number.

## Pulling updates into the other repos

After a new `washuas` version is tagged, each consuming project has to opt in to
it. Updates are **not** pushed automatically.

In the consuming repo:

**1. Bump the version constraint** in its `composer.json` `require` section to
the new tag. Some repos pin an exact tag, others use a caret range — match
whatever that repo already does:

```json
"washuas/washuas": "v1.2.10"
```
```json
"washuas/washuas": "^1.2"
```

> **Note:** A few repos also pin a `reference` inside the `repositories` →
> `washuas/washuas` block. If the repo you're updating does, bump that to the
> new tag as well.

**2. Update the package:**

```bash
ddev composer update washuas/washuas
```

**3. Run database/config updates and rebuild cache**, then commit the updated
`composer.json` and `composer.lock`:

```bash
ddev drush updb -y
ddev drush cr
```

**4. Test locally**, then deploy through that project's normal workflow (see the
repo's own README).

## Requirements

- Drupal core `^9.4 || ^10`
- `drupal/simplesamlphp_auth` `^4.0`

Individual submodules declare their own additional dependencies in their
`*.info.yml` files.
