# Repository Master: Family Hub

## 1. Repo Overview

High-end, touch-optimized family dashboard for an HP EliteDesk running Ubuntu 26.04 LTS (x86_64). The primary visual space container is a Dell P2418HT (1080p) touchscreen monitor. 
The system is intended to be mounted horizontally, but all components MUST be built with flexible, responsive layouts (flex/grid wrapping) so that it COULD be mounted vertically without breaking the UI.

- **Backend:** Laravel 11 / SQLite
- **Frontend:** Vue 3 / Tailwind v4 / shadcn-vue
- **Stack Sync:** Laravel Sail (x86_64)

## 2. Knowledge Architecture

- **Strategy & Roadmap:** `.agents/ROADMAP.md`
- **Engineering Doctrine:** `.agents/GUIDELINES.md`
- **Contextual State:** `.agents/PROJECT_CONTEXT.md`
- **Domain Instructions:** Registered in `.agents/index.yaml`
    - Backend: `.agents/instructions/BACKEND.md`
    - Frontend: `.agents/instructions/FRONTEND.md`
    - Testing: `.agents/instructions/TESTING.md`

## 3. Data Sources & Integrations

- **Calendars:** Apple (CalDAV/iCloud - Priority 1), Google (API), Office 365 (Microsoft Graph API)
- **Weather:** OpenWeatherMap (API)
- **Recipes:** Paprika 3 (Unofficial Sync API)
- **Tasks:** Amazing Marvin (API)

## 4. Custom Skills & Routing

- **Library Docs:** `context7-mcp` (Always use for Laravel, Vue, Tailwind v4, shadcn-vue)
- **Health & Metrics:** `fallow` (Use `make fallow` for structural debt audits)
- **Code Generation:** Use Artisan via `make art c="..."` to ensure consistency.

## 5. Key Working Rules

1. **Interview First:** Before starting any new task, major feature, or phase, you MUST interview the requester to clarify the specific project goal and explicitly verify key architectural or design decisions.
2. **Agile Iteration:** Follow an Agile approach. Build a Minimum Viable Feature (MVF) first, validate it with tests and user feedback, and then iterate. Avoid long-tail "Waterfall" planning for individual features.
3. **Component Ownership:** All `shadcn-vue` components must be generated locally into `resources/js/components/ui/`. We own the code.
4. **Validation Mandate:** No logic change is complete without a corresponding Pest (Backend) or Vitest (Frontend) test. Run `make check` to verify.
5. **No Regressions:** Use `make fallow` to ensure no circular dependencies or dead code are introduced.
