# Frontend Dummy Mode (offline dataset)

A deterministic in-memory dataset lets the whole UI run with ZERO network. Only the `isDummy` flag is persisted.

## Pieces
- `src/dummy/store.ts` - Zustand singleton. State: `isDummy`, `dummyEntities`, `role`. Persists ONLY the flag to `localStorage['dummy:isDummy']` ('1'/absent); entities are never serialized. `toggle()` regenerates a fresh dataset when turning ON; `setDummyGenerator` injects the generator (module-level slot, not in state) and eagerly builds entities if the persisted flag is ON but entities are null.
- `src/dummy/install.ts` - `installDummy()` registers `buildFullDummy` as the generator; idempotent. Called from `src/components/DummyBootstrap.tsx` at module evaluation.
- `src/dummy/index.ts` - barrel + `buildFullDummy(today?)` composition: seeded RNG -> `buildMasterData` -> `buildTransactions` -> `buildAggregates`. Type `FullDummy = Omit<MasterData,'suppliers'> & Transactions & Aggregates` (aggregate suppliers shadow master suppliers).
- `src/dummy/guards.ts` - `withDummyRead(isDummy, dummyValue, realFetch)` short-circuits reads; `commitIfCurrent` discards stale in-flight results; `useDummyRefresh(onChange)` fires when the flag flips (React.StrictMode-safe).
- Sub-modules: `rng.ts` (seeded RNG), `seed.ts` (constants incl. `JABODETABEK_TERRITORIES`), `dates.ts`, `factory.ts`, `factory-transactions.ts`, `aggregates.ts`, `mutations.ts`.

## Rules
- Every READ path must call `withDummyRead`. WRITE paths are not guarded (mutations still hit the API).
- When dummy is ON, `/auth/me` role resolution is replaced by `localStorage.ddp_role` (see Sidebar `useSidebarAuth`).
- Toggle lives in `Topbar`; logout resets dummy state (`reset()`).

## Caution
- This couples production code + bundle to test scaffolding; keep generator imports out of modules that don't need them (store imports the generator lazily via the seam).
