# Family Hub: Project Progress & Context State

*Generated for session restart / context continuity.*

## Project Overview
A high-end, touch-optimized family dashboard designed for a Raspberry Pi 5 and a 24" ViewSonic monitor. The system aggregates multiple calendar sources, weather, tasks, and recipes into a unified "Netflix-style" profile interface.

**Tech Stack:**
- **Backend:** Laravel 11, SQLite
- **Frontend:** Vue 3 (Composition API), Vite, Tailwind CSS v4, shadcn-vue, FullCalendar v6
- **Infra:** Laravel Sail (Docker ARM64)
- **Quality:** Pest, Vitest, PHPStan, Pint, Prettier, Fallow (Codebase Health)

---

## Completed Milestones

### Phase 1: Environment & Foundation ✅
- Initialized Laravel 11 and Docker Sail optimized for ARM64.
- Setup Vue 3, Vite, and Tailwind v4.
- Integrated `shadcn-vue` and generated core components locally.
- Established strict AI Agent guidelines (`GEMINI.md`, `AGENTS.md`, and `.agents/` directory).
- Implemented `Makefile` for streamlined development commands (`make buildFresh`, `make check`, `make fallow`).

### Phase 2: Backend Core & Integrations (Calendar Engines) ✅
Built 4 distinct Calendar sync engines:
1.  **Apple (iCloud):** CalDAV client via `sabre/dav`.
2.  **Google:** Service Account integration via `spatie/laravel-google-calendar`.
3.  **Office 365 (Business):** Client Credentials flow via Microsoft Graph SDK.
4.  **Public Subscriptions:** Direct parser for `.ics` and `webcal://` URLs (e.g., School, Scouts).

### Smart Aggregation & Profiles ✅
- Implemented a relational database schema: `Profile` -> `Tab` -> `Calendar`.
- Created `CalendarManager.php` to fetch and unify events.
- Added **Per-Calendar Refresh Rates**: Events are cached locally in `calendar_events_cache`. Personal calendars can sync instantly, while public feeds sync less frequently.

### Phase 3: Dashboard UI 🎨 ✅
- Built the primary `Dashboard.vue` layout.
- Integrated FullCalendar v6.
- **Touch Optimizations:** Implemented a custom 2-week grid view. Disabled drag-and-drop to prevent accidental touch edits.
- **Orientation Awareness:** The layout now detects aspect ratio and adapts for Vertical (Portrait) or Horizontal (Landscape) mounting.
- **Profile Switcher:** Added a "Netflix-style" profile switcher to filter data.
- **UI Refresh:** Moved weather/clock to the header to maximize calendar space; anchored calendar views to the current time.

---

## Current State & Next Steps

We are currently in **Phase 4: Kitchen & Cooking Experience**.

**Completed in this pass:**
1.  **Layout Refresh:** Implemented the vertical/horizontal flex layout and profile switcher.
2.  **Paprika Foundation:** Built `PaprikaSyncService.php` with authentication, recipe syncing, and local image caching.

**Immediate Next Actions:**
1.  Add the `sync:paprika` artisan command to trigger the service via a cron/scheduler.
2.  Build the "Kitchen Mode" large-format recipe viewer in Vue.
3.  Implement the Meal Plan grid on the dashboard using real Paprika data.

---

## Instructions for Next Agent
1. Read `.agents/PROJECT_CONTEXT.md` and `.agents/ROADMAP.md`.
2. Review this file (`PROGRESS_REPORT.md`) to understand the exact feature state.
3. Resume the Agile Interview process for Phase 4 (Paprika Integration).
