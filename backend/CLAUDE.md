# backend — agent behavior

This file governs *how an agent works* on this backend. *How the code should
look* lives in the skills — read `spec-compliance` and `backend-conventions`
before touching any file here, every session.

## Source of truth

`../ScamTest/SPEC.md` is the product spec. If anything here or in a skill
conflicts with it, `SPEC.md` wins — see "Decision priority" in
`backend-conventions`.

## Before a non-trivial architectural decision

1. Inspect the existing code (what's already there beats assumption).
2. Invoke `grillme` to challenge the proposed approach before writing code —
   for genuine architecture calls (new layer, new dependency, new pattern),
   not for routine CRUD inside the already-agreed structure.
3. Resolve any conflict against `SPEC.md` then `backend-conventions` — never
   by agent preference.
4. Present the plan before implementing it.

## Tooling

- Grill-me — required step above for non-trivial decisions.
- Serena / semantic code index — **not installed this cycle** (see
  `backend-conventions` → Stack → "Deliberately not used this cycle"). Its
  value is navigating a large or foreign codebase; this one starts empty.
  Revisit only once the codebase has grown enough to justify it, and only
  after running the tool-vetting prompts from the ДЗ materials (ask the
  agent what it installs and does, check the repo for safety, get an
  explicit go/no-go) before adding a new MCP server.
- Don't introduce any other new tool, package, or pattern without flagging it
  first — see `backend-conventions` → Definition of done.

## Agent roles

Defined in `.claude/agents/`: A (domain), B (API), C (infra), D (tests) build
in parallel, each confined to its own territory (see `backend-conventions` →
Agent discipline). The `backend-review` workflow (two independent reviewers +
an agent-breaker) runs after each meaningful change — it's a repeatable
procedure, not a one-time step. The orchestrating session (not any subagent)
integrates the pieces and resolves cross-territory conflicts.
