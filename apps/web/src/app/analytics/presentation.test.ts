/**
 * RED cycle: insight presentation helpers (delta chip + trust labels).
 *
 * Given a delta payload, the chip text/type must render id-ID decimals and
 * never show an "up" chip while displaying 0,0%. Trust labels map the
 * backend sufficiency level to id-ID copy and omit absent metadata.
 */
import { formatDeltaChip, trustLabel, methodLabel, measurementNote } from './presentation';

describe('formatDeltaChip', () => {
  it('renders a positive delta with a comma decimal and an up chip', () => {
    expect(formatDeltaChip({ delta_percent: 20.0, direction: 'up' })).toEqual({
      text: '+20,0%',
      type: 'up',
    });
  });

  it('renders a negative delta with a down chip', () => {
    expect(formatDeltaChip({ delta_percent: -20.0, direction: 'down' })).toEqual({
      text: '-20,0%',
      type: 'down',
    });
  });

  it('renders a null delta as a neutral "no comparison" chip', () => {
    expect(formatDeltaChip({ delta_percent: null, direction: 'neutral' })).toEqual({
      text: 'belum ada pembanding',
      type: 'neutral',
    });
  });

  it('renders a zero delta as 0,0% neutral', () => {
    expect(formatDeltaChip({ delta_percent: 0.0, direction: 'neutral' })).toEqual({
      text: '0,0%',
      type: 'neutral',
    });
  });

  it('never shows an up chip while displaying 0,0%', () => {
    const chip = formatDeltaChip({ delta_percent: 0.0, direction: 'up' });
    expect(chip.type).not.toBe('up');
  });
});

describe('trustLabel', () => {
  it('maps insufficient to id-ID copy', () => {
    expect(trustLabel('insufficient')).toBe('Data belum cukup');
  });

  it('maps limited and adequate to distinct id-ID labels', () => {
    const limited = trustLabel('limited');
    const adequate = trustLabel('adequate');
    expect(limited).toBeTruthy();
    expect(adequate).toBeTruthy();
    expect(limited).not.toBe(adequate);
    expect(limited).not.toBe(trustLabel('insufficient'));
  });

  it('returns null for an unknown or absent level', () => {
    expect(trustLabel('mystery')).toBeNull();
    expect(trustLabel(undefined)).toBeNull();
  });
});

describe('methodLabel', () => {
  it('returns the method string when present', () => {
    expect(methodLabel('dummy-heuristic')).toBe('dummy-heuristic');
  });

  it('returns null when absent', () => {
    expect(methodLabel(undefined)).toBeNull();
    expect(methodLabel('')).toBeNull();
  });
});

describe('measurementNote', () => {
  it('returns the note when present', () => {
    expect(measurementNote({ measured: true, note: 'Measurement aktif.' })).toBe(
      'Measurement aktif.',
    );
  });

  it('returns null when measurement is absent (no "undefined" text)', () => {
    expect(measurementNote(undefined)).toBeNull();
    expect(measurementNote({ measured: true })).toBeNull();
  });
});
