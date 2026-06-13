# Project Roadmap: Family Hub

## Current Status: Phase 1 (Foundation) Complete ✅

- [x] Laravel 11 / Vue 3 / Vite Setup
- [x] Docker Sail (ARM64) Configuration
- [x] shadcn-vue & Tailwind v4 Integration
- [x] Testing & Quality Suite (Pest, Vitest, Fallow, PHPStan)
- [x] Agent Infrastructure (.agents/ & GEMINI.md)

## Phase 2: Backend Core & Integrations 🏗️

- [ ] **Apple Calendar (Priority 1):** CalDAV client via `sabre/dav` (iCloud support).
- [ ] **Google Calendar:** Auth & 2-way Sync via `spatie/laravel-google-calendar`.
- [ ] **Office 365:** Microsoft Graph API integration.
- [ ] **Weather API:** OpenWeatherMap integration with local caching.
- [ ] **Paprika 3:** Unofficial Cloud Sync API integration for recipes & meal plans.
- [ ] **Amazing Marvin:** Task fetching and collaborative marking.
- [ ] **Normalization:** Unified Event/Task/Recipe data models.

## Phase 3: High-End Dashboard UI 🎨 (Complete) ✅
- [x] **Touch Optimization:** FullCalendar v6 with interaction plugins.
- [x] **Dynamic Tabs:** Tab switching logic (Family, Meal Plan, Recipes, Tasks).
- [x] **Sidebar Toggles:** Real-time visibility controls for individual calendars.
- [x] **Glassmorphism Theme:** High-end aesthetic with rich spacing and typography.

## Phase 4: Kitchen & Cooking Experience 🍳

- [ ] **Recipe Browser:** Grid view of Paprika library with high-res caching.
- [ ] **Kitchen Mode:** Large-format view with touch-to-complete interactions.
- [ ] **Meal Plan Calendar:** Integrated recipe links within the calendar grid.

## Phase 5: Raspberry Pi Deployment 🥧

- [ ] **ARM64 Image Optimization:** Final Docker tuning for Pi 5 performance.
- [ ] **Kiosk Mode Script:** Auto-launching Chromium in full-screen touch mode on boot.
- [ ] **Offline Resilience:** Local SQLite caching strategy for all cloud data.

## Future Ideas 🚀

- [ ] Photo Slideshow (Apple Photos/Google Photos)
- [ ] Grocery List sync (Amazing Marvin/Paprika)
- [ ] Security Camera (RTSP) stream integration
