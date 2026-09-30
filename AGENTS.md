# Project Instructions — Digital Distribution Management Platform

This file is loaded as context for every Pi session in this project. It documents conventions, tooling, and mandatory tooling integrations.

---

## ⚡ Mandatory Tooling Integrations

**Every Pocket-to-PI skill invocation MUST use the following MCP servers when relevant:**

| Server | Purpose | When to call |
|--------|---------|--------------|
| **serena** | Semantic code navigation, LSP-backed edit/search/refactor | Any code modification, symbol lookup, reference finding, rename, diagnostics — **MUST** run MCP Bootstrap (`pi --approve mcp reconnect` + `serena project health-check`) before the first `serena_*` call |
| **graphify** | Whole-repo knowledge graph, architectural queries | Any question about "how X works", cross-module impact, onboarding, design decisions — **MUST** run MCP Bootstrap before the first `graphify` query |

> **Rule of thumb:** if the task touches **code**, reach for **serena** first; if the task needs **context/architecture/impact**, reach for **graphify** first. They are complementary — use both.

## 🟢 MCP Bootstrap — Pastikan server ready sebelum pakai tool

Setiap skill yang memakai Serena atau Graphify **MUST** menjalankan bootstrap ini dulu (sekali per sesi Pi, atau sekali per subagent baru). Jika server sudah hidup, `reconnect` selesai instan — tidak ada efek samping.

```bash
# 1) Pastikan adapter MCP ter-install dan di-approve (sekali per repo)
pi --approve mcp install

# 2) Reconnect bila server mati / belum listening (aman dipanggil tiap sesi)
pi --approve mcp reconnect

# 3) Verifikasi keduanya listening
pi --approve mcp list
# Expected: serena (listening), graphify (listening)

# 4) Aktifkan project Serena — memuat LSP, memories, dan symbol index
serena project health-check   # alternatif: serena activate-project
```

> Skill helper internal: `/mcp-bootstrap` — cukup panggil ini di awal skill lain, lalu lanjut ke `serena_*` / `graphify_*` / `graphify` CLI. Tidak perlu tulis ulang perintah `pi --approve mcp ...` di tiap skill.

### How to invoke in practice

- **Serena tools** are exposed as `serena_<tool>` via `pi-mcp-adapter` (e.g., `serena_find_symbol`, `serena_replace_symbol_body`, `serena_get_diagnostics_for_file`). Legacy Pi built-in MCP uses `mcp__serena__<tool>` — both resolve to the same server.
- **Graphify** is available via:
  - Direct CLI: `graphify query "<question>"`, `graphify path "A" "B"`, `graphify explain "X"`
  - MCP (when `graphify-mcp` is running): `graphify_<tool>` (e.g., `graphify_search_nodes`) — check `pi --approve mcp list` or `/mcp` for live tool list.

### Pocket skill examples with mandatory integrations

> **Step 0 for every example below:** `/mcp-bootstrap` (`pi --approve mcp reconnect` + `serena project health-check`) — ensures Serena & Graphify are listening before any tool call.

```text
/pocketto:bug-hunting "checkout total off by 1 cent"
  → Step 0: /mcp-bootstrap
  → MUST call serena: find_symbol "checkout", find_referencing_symbols, get_diagnostics_for_file
  → MAY call graphify: query "how is checkout total calculated across modules?"

/pocketto:hotfix "bump rate-limit window to 60s"
  → Step 0: /mcp-bootstrap
  → MUST call serena: find_symbol "rateLimit", replace_symbol_body, rename_symbol

/pocketto:pocket-grinding "add dark mode toggle"
  → Step 0: /mcp-bootstrap
  → MUST call graphify: query "current theming approach and UI token structure"
  → MAY call serena: find_symbol "theme" after graphify identifies target files

/pocketto:pocket-planning
  → Step 0: /mcp-bootstrap
  → MUST call graphify: query "which modules are affected by the acceptance criteria?"

/pocketto:pocket-development
  → Step 0: /mcp-bootstrap (parent + each subagent packet)
  → Each subagent packet MUST include serena tools for edits, graphify for context

/pocketto:pocket-closing <plan_dir>
  → Step 0: /mcp-bootstrap
  → MUST commit ALL remaining changes (traveling state, plan docs, code) before closing
  → MUST run `graphify update .` after the commit so the knowledge graph reflects the closed phase
  → MUST verify the graph refreshed (graph.json / GRAPH_REPORT.md timestamps) before reporting CLOSED
```

---

## 🔒 Mandatory Rules for `pocket-closing`

`pocket-closing` is the terminal stage. In this project it is **not done** until both of these happen, in order:

1. **Commit all changes.**
   Every pending change must be committed before the plan is closed — no dangling work left behind. This includes the code, the traveling Pocket state (`log.json`, `closeout.md`, `execution-plan/`), and any review artifacts.

   ```bash
   git status --short                 # inspect what is pending
   git add -A                         # stage everything (excluding gitignored paths)
   git commit -m "chore(pocket): close <plan-name>"
   ```

   > If `graphify-out/` or other generated artifacts are gitignored, they stay out of the commit — that is expected.

2. **Refresh the knowledge graph.**
   After the commit lands, rebuild the graph so the next session (and the next Pocket phase) starts from an accurate picture:

   ```bash
   graphify update .
   ```

   Confirm it actually refreshed:

   ```bash
   stat -c '%y %n' graphify-out/graph.json graphify-out/GRAPH_REPORT.md
   ```

**Order matters:** commit first, then `graphify update .`. The graph must describe the committed state, not an uncommitted working tree.

**Do not report `CLOSED`** until the commit exists and `graphify update .` has completed successfully. If either step fails, surface the failure instead of closing silently.

---

## 🛠️ MCP Server Status

Both servers are configured in `.pi/mcp-adapter.json` and approved via `settings.projectServers = "allow"`.

```bash
# Bootstrap (run at session start — safe to run every time)
pi --approve mcp reconnect
pi --approve mcp list          # verify live

# If a server shows not listening / 0 tools, reconnect again:
pi --approve mcp reconnect
```

Expected output includes:
- **Context7** (2 tools, listening)
- **Serena** (29 tools, listening — code editing/analysis)
- **Graphify** (16 tools, listening — from `graphify-mcp`)

> If `pi --approve mcp list` shows `not listening` or `0 tools`, do **not** proceed to Serena/Graphify calls — run `pi --approve mcp reconnect` first and re-verify. Fallback: CLI `graphify query` still works even when MCP is down, but Serena LSP calls will fail.

---

## 🔧 Serena Project Config

`.serena/project.yml` includes both language servers:

```yaml
language_servers:
  - php       # apps/api (Laravel)
  - typescript # apps/web (Next.js) + packages/shared
```

Run health check:
```bash
serena project health-check
```

Memories (auto-loaded on activation):
```
.serena/memories/conventions.md
.serena/memories/database.md
.serena/memories/api/{auth,core,domain,request_flow}.md
.serena/memories/web/{core,dummy_mode}.md
...
```

---

## 🕸️ Graphify State

Graph built and up-to-date in `graphify-out/`:

```bash
graphify update .          # incremental refresh
graphify query "..."       # ask the graph
graphify path "A" "B"      # shortest path
graphify explain "X"       # plain-language node summary
```

Key outputs:
- `graphify-out/graph.json` — raw graph
- `graphify-out/GRAPH_REPORT.md` — audit report (God Nodes, Surprising Connections, Suggested Questions)
- `graphify-out/graph.html` — interactive visual

---

## 📦 Pocket-to-PI Skills Available

| Skill | Trigger | Typical use |
|-------|---------|-------------|
| `pocket-pitching` | "pitch this", "explore idea" | Pre-grinding exploration |
| `pocket-grinding` | "brainstorm", "plan this" | BDD spec + acceptance criteria |
| `pocket-planning` | "create plan", auto from grinding | TDD execution plan |
| `pocket-structuring` | "structure plan", auto from planning | Task files + index |
| `pocket-development` | "execute plan", "delegate tasks" | Subagent execution with audit |
| `pocket-closing` | "close the plan", after dev pass | Reconcile, gate, summarize |
| `bug-hunting` | "fix bug", "debug" | Systematic debugging |
| `hotfix` | "quick fix", "small change" | Fast gated changes |
| `brand-design` | "design system", "creative brief" | UI tokens & brand |
| `structured-research` | "validate assumption" | Evidence-based verdict |
| `pocket-help` | "what is pocket" | Router / onboarding |
| `pocket-init` | "onboard this project" | Generate AGENTS.md, enable enterprise |
| `create-pr` | "open a PR" | Enterprise PR recorder |

---

## 🧭 Quick Reference Commands

```bash
# Pocket flow
/pocketto:pocket-grinding   "feature description"
/pocketto:pocket-planning
/pocketto:pocket-development
/pocketto:pocket-closing    <plan_dir>

# Standalone
/pocketto:bug-hunting   "symptom"
/pocketto:hotfix        "change description"
/pocketto:structured-research "assumption to validate"

# MCP verification
pi --approve mcp list

# Serena
serena project health-check
serena memories list

# Graphify
graphify update .
graphify query "question"
graphify explain "node"
```

---

## 📁 Project Structure (Monorepo)

```
apps/api        # Laravel 11 + PHP 8.3
apps/web        # Next.js 16 + React 18 + TS
packages/shared # @ddp/shared (TS)
docker/         # Compose dev stack
docs/           # Technical docs + pocket plans
.serena/        # Serena project config + memories
graphify-out/   # Knowledge graph artifacts
.pi/            # Pi config (mcp-adapter.json, tasks/)
```

---

## 🧪 Testing Commands

```bash
# API (Laravel)
cd apps/api && php artisan test
cd apps/api && ./vendor/bin/pint  # lint

# Web (Next.js)
cd apps/web && npm run lint
cd apps/web && npm run build
cd apps/web && npm test
```

---

*Generated by Pi session — keep this file up to date as tooling evolves.*