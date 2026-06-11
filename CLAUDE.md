# alex-movie-theater — Claude Code Context

## What This File Is

AI assistant context for this project. Built on the Aslan Advisors tech stack
(PHP 8+, MySQL, GoDaddy/cPanel). See `aslan` master skill for cross-project
conventions, lifecycle routing, and defaults.

**Project type:** `hybrid` (see `.claude/project-type`). On non-trivial
tasks, the `aslan` master skill enforces a workflow gate — `workflow-brainstorm`
must produce an approved spec before any code lands.

## Skill routing
<!-- aslan-skills:install -->

For any work in this repo, invoke the `aslan` master skill first. It will
route to the right specialist (workflow / frontend / backend / data / deploy /
security / quality / client-lifecycle) based on what you're trying to do.

For any non-trivial task (new file, new schema column, new feature, new
dependency), `aslan` routes to the `workflow` mother FIRST — brainstorm and
plan before code. Trivial work (copy fixes, one-line CSS, README edits,
dependency bumps, typos) bypasses.

To override the workflow gate on a legitimate fast-fix moment, type
`skip workflow:` followed by what you want. The override is logged in
the commit message footer.

## Project-specific notes

[Add project-specific overrides, exceptions, and gotchas here.]
