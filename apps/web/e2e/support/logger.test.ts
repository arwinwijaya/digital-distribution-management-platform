import * as fs from 'fs';
import * as os from 'os';
import * as path from 'path';
import { createLogger } from './logger';

describe('JSONL logger shared helper', () => {
  let tempDir: string;

  beforeEach(() => {
    tempDir = fs.mkdtempSync(path.join(os.tmpdir(), 'ddp-jsonl-'));
  });

  afterEach(() => {
    fs.rmSync(tempDir, { recursive: true, force: true });
  });

  test('writes success event with required fields', () => {
    const runId = 'test-run-001';
    const logger = createLogger(runId, { dir: tempDir });

    const event = logger.logEvent({
      role: 'outlet',
      step: 'order-submission',
      action: 'POST /api/orders',
      url: 'http://localhost/api/orders',
      selector: '',
      apiStatus: 201,
      durationMs: 120,
      status: 'success',
    });

    expect(event.runId).toBe(runId);
    expect(typeof event.timestamp).toBe('string');
    expect(event.role).toBe('outlet');
    expect(event.step).toBe('order-submission');
    expect(event.action).toBe('POST /api/orders');
    expect(event.url).toBe('http://localhost/api/orders');
    expect(event.selector).toBe('');
    expect(event.apiStatus).toBe(201);
    expect(event.durationMs).toBe(120);
    expect(event.status).toBe('success');
    expect(event.error).toBeNull();
    expect(event.attachments).toEqual([]);

    // Verify the file contains one line with all required fields
    const content = fs.readFileSync(logger.filePath, 'utf8');
    const lines = content.trim().split('\n');
    expect(lines).toHaveLength(1);
    const parsed = JSON.parse(lines[0]);
    expect(parsed.runId).toBe(runId);
    expect(parsed.timestamp).toMatch(
      /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/,
    );
    expect(parsed.role).toBe('outlet');
    expect(parsed.step).toBe('order-submission');
    expect(parsed.action).toBe('POST /api/orders');
    expect(parsed.url).toBe('http://localhost/api/orders');
    expect(parsed.selector).toBe('');
    expect(parsed.apiStatus).toBe(201);
    expect(parsed.durationMs).toBe(120);
    expect(parsed.status).toBe('success');
    expect(parsed.error).toBeNull();
    expect(parsed.attachments).toEqual([]);
  });

  test('writes failure event with error + attachments', () => {
    const runId = 'test-run-002';
    const logger = createLogger(runId, { dir: tempDir });

    const event = logger.logEvent({
      role: 'admin',
      step: 'delivery-assign',
      action: 'POST /api/deliveries',
      url: 'http://localhost/api/deliveries',
      selector: '[data-testid="admin-orders-table"]',
      apiStatus: 422,
      durationMs: 80,
      status: 'failed',
      error: 'Only confirmed orders can be assigned',
      attachments: ['/tmp/screenshot-1.png', '/tmp/trace.zip'],
    });

    expect(event.status).toBe('failed');
    expect(event.error).toBe('Only confirmed orders can be assigned');
    expect(event.attachments).toEqual(['/tmp/screenshot-1.png', '/tmp/trace.zip']);

    const content = fs.readFileSync(logger.filePath, 'utf8');
    const lines = content.trim().split('\n');
    expect(lines).toHaveLength(1);
    const parsed = JSON.parse(lines[0]);
    expect(parsed.status).toBe('failed');
    expect(parsed.error).toBe('Only confirmed orders can be assigned');
    expect(parsed.attachments).toEqual(['/tmp/screenshot-1.png', '/tmp/trace.zip']);
  });

  test('runId correlation across multiple events', () => {
    const runId = 'test-run-003';
    const logger = createLogger(runId, { dir: tempDir });

    const event1 = logger.logEvent({ role: 'outlet', step: 'login', action: 'login', durationMs: 100 });
    const event2 = logger.logEvent({ role: 'outlet', step: 'catalog', action: 'browse', durationMs: 200 });

    expect(event1.runId).toBe(runId);
    expect(event2.runId).toBe(runId);

    // Both timestamps are ISO strings and parseable
    expect(new Date(event1.timestamp).toISOString()).toBe(event1.timestamp);
    expect(new Date(event2.timestamp).toISOString()).toBe(event2.timestamp);

    // File has two lines
    const content = fs.readFileSync(logger.filePath, 'utf8');
    const lines = content.trim().split('\n');
    expect(lines).toHaveLength(2);
    const parsed1 = JSON.parse(lines[0]);
    const parsed2 = JSON.parse(lines[1]);
    expect(parsed1.runId).toBe(runId);
    expect(parsed2.runId).toBe(runId);
  });
});
