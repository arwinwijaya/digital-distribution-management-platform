import React from 'react';
import '@testing-library/jest-dom';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';

// ─── Canvas doubles (jsdom has no 2D context without the `canvas` package) ───
const contextStub = {
  clearRect: jest.fn(),
  beginPath: jest.fn(),
  moveTo: jest.fn(),
  lineTo: jest.fn(),
  stroke: jest.fn(),
  fillRect: jest.fn(),
  set lineWidth(_v: number) {},
  set strokeStyle(_v: string) {},
  set lineCap(_v: string) {},
  set lineJoin(_v: string) {},
};

HTMLCanvasElement.prototype.getContext = jest.fn(() => contextStub) as unknown as typeof HTMLCanvasElement.prototype.getContext;

const mockToBlob = jest.fn((callback: BlobCallback) => {
  callback(new Blob(['fake-png-data'], { type: 'image/png' }));
});
HTMLCanvasElement.prototype.toBlob = mockToBlob as unknown as typeof HTMLCanvasElement.prototype.toBlob;

function createFile(name: string, type: string): File {
  return new File(['fake-content'], name, { type });
}

// jsdom does not implement object URLs used for the photo preview.
beforeAll(() => {
  if (typeof URL.createObjectURL !== 'function') {
    Object.defineProperty(URL, 'createObjectURL', { configurable: true, value: jest.fn(() => 'blob:preview') });
    Object.defineProperty(URL, 'revokeObjectURL', { configurable: true, value: jest.fn() });
  }
});

const mockUploadProof = jest.fn();

jest.mock('@/app/delivery/api', () => ({
  uploadProof: (...args: unknown[]) => mockUploadProof(...args),
}));

import PodCapture from '@/components/delivery/PodCapture';

function drawSignature() {
  const canvas = screen.getByTestId('pod-signature-canvas') as HTMLCanvasElement;
  fireEvent.mouseDown(canvas, { clientX: 10, clientY: 10 });
  fireEvent.mouseMove(canvas, { clientX: 40, clientY: 40 });
  fireEvent.mouseUp(canvas, { clientX: 40, clientY: 40 });
  return canvas;
}

function pickPhoto() {
  const fileInput = screen.getByLabelText(/foto/i) as HTMLInputElement;
  fireEvent.change(fileInput, { target: { files: [createFile('photo.jpg', 'image/jpeg')] } });
}

describe('PodCapture — driver PoD capture', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockUploadProof.mockResolvedValue({
      id: 1,
      order_id: 1,
      driver_id: 1,
      status: 'delivered',
      delivered_at: '2026-09-22T08:00:00Z',
      proof_of_delivery: {
        photo_url: 'http://example.com/photo.png',
        signature_url: 'http://example.com/sig.png',
      },
    });
  });

  it('renders photo input, signature canvas, and submit button', () => {
    render(<PodCapture deliveryId={1} onSuccess={() => {}} />);

    expect(screen.getByLabelText(/foto/i)).toBeInTheDocument();
    expect(screen.getByTestId('pod-signature-canvas')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /kirim bukti/i })).toBeInTheDocument();
  });

  it('disables submit until BOTH photo and signature are present', () => {
    render(<PodCapture deliveryId={1} onSuccess={() => {}} />);

    const submitBtn = screen.getByRole('button', { name: /kirim bukti/i });
    expect(submitBtn).toBeDisabled();

    // Photo only -> still disabled.
    pickPhoto();
    expect(submitBtn).toBeDisabled();

    // Photo + signature -> enabled.
    drawSignature();
    expect(submitBtn).not.toBeDisabled();
  });

  it('disables submit when only signature is drawn', () => {
    render(<PodCapture deliveryId={1} onSuccess={() => {}} />);

    drawSignature();
    expect(screen.getByRole('button', { name: /kirim bukti/i })).toBeDisabled();
  });

  it('calls uploadProof with a FormData carrying photo + signature', async () => {
    render(<PodCapture deliveryId={42} onSuccess={() => {}} />);

    pickPhoto();
    drawSignature();
    fireEvent.click(screen.getByRole('button', { name: /kirim bukti/i }));

    await waitFor(() => {
      expect(mockUploadProof).toHaveBeenCalledWith(42, expect.any(FormData));
    });

    const formData = mockUploadProof.mock.calls[0][1] as FormData;
    expect(formData.get('photo')).toBeInstanceOf(File);
    expect(formData.get('signature')).toBeInstanceOf(File);
  });

  it('invokes onSuccess after a successful upload', async () => {
    const onSuccess = jest.fn();
    render(<PodCapture deliveryId={1} onSuccess={onSuccess} />);

    pickPhoto();
    drawSignature();
    fireEvent.click(screen.getByRole('button', { name: /kirim bukti/i }));

    await waitFor(() => expect(onSuccess).toHaveBeenCalledTimes(1));
  });

  it('shows an error message when upload fails', async () => {
    mockUploadProof.mockRejectedValueOnce(new Error('Unggahan bukti gagal.'));
    render(<PodCapture deliveryId={1} onSuccess={() => {}} />);

    pickPhoto();
    drawSignature();
    fireEvent.click(screen.getByRole('button', { name: /kirim bukti/i }));

    await waitFor(() => {
      expect(screen.getByText(/unggahan bukti gagal/i)).toBeInTheDocument();
    });
  });

  it('clears the signature (and re-disables submit) when "Hapus" is clicked', () => {
    render(<PodCapture deliveryId={1} onSuccess={() => {}} />);

    pickPhoto();
    drawSignature();
    expect(screen.getByRole('button', { name: /kirim bukti/i })).not.toBeDisabled();

    fireEvent.click(screen.getByRole('button', { name: /hapus tanda tangan/i }));

    expect(contextStub.clearRect).toHaveBeenCalled();
    expect(screen.getByRole('button', { name: /kirim bukti/i })).toBeDisabled();
  });
});
