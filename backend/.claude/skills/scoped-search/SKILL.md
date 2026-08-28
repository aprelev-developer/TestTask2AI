---
name: scoped-search
description: Confines file search and reads to the project directory. Use before any find/grep/glob/ls/Read that isn't obviously scoped to the current project root, before guessing a file's location, or whenever a search inside the project turns up nothing and the next instinct is to look elsewhere (Downloads, Desktop, home directory, other projects).
---

# Scoped search

The project root is the only place to search or read without asking. Never touches
outside it are a boundary, not a default to relax when the project looks empty or
the file isn't where expected.

## Steps

1. Search and read strictly under the project root (`pwd`/the working directory git
   reports). Empty results stay empty — do not widen the search path on your own.
2. If the file plausibly exists outside the project root, stop and ask the user
   where it is, offering candidates if you found any, instead of searching for it
   yourself. Do not run `find`/`grep`/`ls` against `~`, `Downloads`, `Desktop`, or
   any other project's directory.
3. Only search outside the project root after the user names a location or gives
   explicit permission for that turn.
