# Project Guidelines & Doctrine

## 1. Architectural Mandates

- **Domain Driven:** Keep logic grouped by integration domain (e.g., `App\Services\Calendar`, `App\Services\Paprika`).
- **Data Normalization:** All external API data must be converted to a unified local Resource/DTO before being passed to the frontend.
- **Composition over Inheritance:** Prefer small, focused Vue composables and Laravel services over deep inheritance trees.
- **Component Ownership:** All UI components from `shadcn-vue` must live in `resources/js/components/ui/`. Modification of these components is encouraged to match the touch-screen requirements.

## 2. Touch UI Standards (Dell P2418HT)

- **Target Size:** Interactive elements (buttons, inputs, tabs) must have a minimum touch target of 44x44px.
- **Visual Feedback:** All touch interactions must provide immediate visual feedback (active states, ripples, or transitions).
- **Kitchen Visibility:** Typography for "Kitchen Mode" recipes should be readable from 5 feet away (minimum 1.25rem/20px).
- **Orientation Resilience:** The dashboard is mounted horizontally, but MUST use flex/grid wrapping to ensure it adapts perfectly to a vertical orientation if rotated.

## 3. Engineering Quality

- **Testing Requirements:**
    - No backend logic may ship without a Pest test.
    - Complex frontend components must have a Vitest spec.
    - Use `make test` to verify the full suite.
- **Static Analysis:** PHPStan must pass at Level 5 or higher.
- **Code Health (Fallow):**
    - Maximize code reachability (Dead code < 5%).
    - Zero circular dependencies in `resources/js/`.
    - Minimize duplication in UI logic.

## 4. Integration Protocol

- **Rate Limiting:** Always implement local caching (SQLite) for external APIs (Paprika, Weather, Marvin) to stay within free tier limits and provide offline support.
- **Secrets Management:** Never commit API keys. Use `.env` and `.env.example`.
- **Sync Strategy:** Use incremental sync wherever possible (checking timestamps/UUIDs) to minimize payload sizes.

## 5. Agent Instructions

- **Read First:** Agents must read `GEMINI.md` and `.agents/PROJECT_CONTEXT.md` at the start of every session.
- **Verify Often:** Run `make check` before declaring any task complete.
- **Documentation:** Use `context7-mcp` to resolve any library ambiguities.
