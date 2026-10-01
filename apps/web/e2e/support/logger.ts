import * as fs from 'fs';
import * as path from 'path';

export type LogEvent = {
  runId: string;
  timestamp: string;
  role: string;
  step: string;
  action: string;
  url: string;
  selector: string;
  apiStatus: number | null;
  durationMs: number;
  status: 'success' | 'failed';
  error: string | null;
  attachments: string[];
};

export function createLogger(runId: string, opts?: { dir?: string }) {
  const dir = opts?.dir ?? path.resolve('test-results', 'e2e-logs');
  fs.mkdirSync(dir, { recursive: true });
  const filePath = path.join(dir, `${runId}.jsonl`);

  function logEvent(input: Partial<LogEvent>): LogEvent {
    const event: LogEvent = {
      runId,
      timestamp: input.timestamp ?? new Date().toISOString(),
      role: input.role ?? '',
      step: input.step ?? '',
      action: input.action ?? '',
      url: input.url ?? '',
      selector: input.selector ?? '',
      apiStatus: input.apiStatus ?? null,
      durationMs: input.durationMs ?? 0,
      status: input.status ?? 'success',
      error: input.error ?? null,
      attachments: input.attachments ?? [],
    };
    fs.appendFileSync(filePath, `${JSON.stringify(event)}\n`, { encoding: 'utf8' });
    return event;
  }

  return { logEvent, filePath };
}
