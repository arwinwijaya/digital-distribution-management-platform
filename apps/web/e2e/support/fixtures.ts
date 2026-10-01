import { test as base } from '@playwright/test';

export const test = base.extend<{ runId: string }>({
  runId: async ({}, use, testInfo) => {
    const runId = `e2e-${new Date().toISOString().replace(/[:.]/g, '-')}-${testInfo.workerIndex}`;
    await use(runId);
  },
});

export { expect } from '@playwright/test';
