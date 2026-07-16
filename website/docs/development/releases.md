---
sidebar_position: 1
title: Releasing
description: How versions are cut and published to Packagist
---

# Releasing

Releases are automated. Merging pull requests into `main` is all a maintainer
normally does — versioning, the changelog, the git tag, the GitHub Release, and
the Packagist update all happen from there.

## How it works

We use [release-please](https://github.com/googleapis/release-please) with
[Conventional Commits](https://www.conventionalcommits.org/). The pipeline is:

1. PRs are **squash-merged** into `main`. The PR title becomes the commit
   subject on `main`.
2. release-please reads those commit subjects, works out the next version, and
   maintains a rolling **release pull request** that updates `CHANGELOG.md` and
   the `Version:` header in `plugin.php`.
3. Merging that release PR creates the git tag and the GitHub Release.
4. Packagist picks up the new tag automatically via the GitHub webhook and
   publishes the version.

Because the version is derived from the git tag, `composer.json` intentionally
has **no `version` field** — do not add one.

## PR titles must follow Conventional Commits

This is the one rule contributors need to follow. Since PRs are squash-merged,
the **PR title** is what release-please parses, and a non-conforming title
contributes nothing to the changelog or the version bump. PR titles are linted
in CI, so a bad title blocks the merge.

The format is:

```
<type>[optional scope]: <description>
```

Common types and their effect on the version (once the package is `>= 1.0.0`):

| Type | Example | Version bump |
|---|---|---|
| `fix` | `fix: guard against missing field type` | patch (`1.0.0` → `1.0.1`) |
| `feat` | `feat: add repeater field type` | minor (`1.0.0` → `1.1.0`) |
| `feat!` / `BREAKING CHANGE:` footer | `feat!: rename DataView storage API` | major (`1.0.0` → `2.0.0`) |
| `docs`, `chore`, `refactor`, `test`, `ci`, `build`, `perf`, `style` | `docs: expand field-type examples` | no release on its own |

:::note Pre-1.0 behaviour
While the package is still on `0.x`, release-please never bumps to `1.0.0` on
its own — a breaking change bumps the **minor** instead (`0.1.0` → `0.2.0`). The
`1.0.0` release is cut deliberately.
:::

### Breaking changes

Signal a breaking change either by appending `!` after the type
(`feat!: ...`) or by adding a footer to the commit body:

```
feat: rename DataView storage API

BREAKING CHANGE: `storage` now expects an adapter instance instead of a string.
```

With squash merges the PR body becomes the commit body, so a stray
`BREAKING CHANGE:` line in a PR description will also trigger a major bump —
keep descriptions clean and put the intent in the title.

## Version constraints for consumers

Once published, downstream projects install a released version rather than
`dev-main`:

```bash
composer require tangible/object:^1.0
```

- `^1.0` — receives all backwards-compatible minors and patches below `2.0.0`
  (the conventional default).
- `~1.4.0` — patches only (`1.4.x`), no minor upgrades.
- Consumers are never upgraded silently: `composer.lock` pins the exact
  installed version until they run `composer update`.
