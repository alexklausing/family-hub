# Family Hub Agent Mandates

Instructions in this file are foundational and must be followed by every agent.

## 1. Interaction Protocol: The Interview

Before executing any Directive (implementation request), you MUST:

- **Clarify Goals:** Ask 1-3 targeted questions to ensure the specific project goal is understood.
- **Verify Decisions:** Explicitly state your intended architectural or design choices and ask for confirmation.
- **Wait:** Do not begin implementation until the requester has acknowledged or adjusted your proposed path.

## 2. Engineering Lifecycle: Agile Only

- **Small Batches:** Break features down into the smallest possible functional increments.
- **Iterative Delivery:** Research -> Implement MVF -> Validate -> Get Feedback -> Refine.
- **No Waterfall:** Do not plan out the entire implementation of a feature before starting the first small step.

## 3. Technical Reference

Strictly follow the architecture, skills, and rules defined in **AGENTS.md**.

## 4. Quality Guardrails

- **Verification:** `make test` must be run after every incremental change.
- **Health Audit:** `make fallow` must be run before finalizing any feature.
- **Linting:** `make lint` and `make format` are mandatory.

Refer to `.agents/ROADMAP.md` for current milestone status and `.agents/GUIDELINES.md` for detailed technical doctrine.
