import React from 'react';
import '@testing-library/jest-dom';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import PodCapture from '@/components/delivery/PodCapture';
import { uploadProof } from '@/app/delivery/api';

jest.mock('@/app/delivery/api', () => ({
  uploadProof: jest.fn(),
}));

const mockedUploadProof = uploadProof as jest.MockedFunction<typeof uploadProof>;

function drawSignature(): void {
  const canvas = screen.getByTestId('pod-signature-canvas');
  fireEvent.mouseDown(canvas, { clientX: 10, clientY: 10 });
  fireEvent.mouseMove(canvas, { clientX: 80, clientY: 40 });
  fireEvent.mouseUp(canvas);
}

describe('PodCapture', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    HTMLCanvasElement.prototype.toBlob = jest.fn((callback: BlobCallback) => {
      callback(new Blob(['signature'], { type: 'image/png' }));
    });
    mockedUploadProof.mockResolvedValue({ status: 'success', data: {} });
  });

  it('submits the selected photo and PNG signature', async () => {
    render(<PodCapture deliveryId={42} />);
    const photo = new File(['photo'], 'pod.jpg', { type: 'image/jpeg' });

    fireEvent.change(screen.getByLabelText(/foto bukti/i), { target: { files: [photo] } });
    drawSignature();
    fireEvent.click(screen.getByRole('button', { name: /kirim bukti/i }));

    await waitFor(() => expect(mockedUploadProof).toHaveBeenCalledTimes(1));
    expect(mockedUploadProof).toHaveBeenCalledWith(42, {
      photo,
      signature: expect.objectContaining({ type: 'image/png' }),
    });
  });

  it('keeps submit disabled until both photo and signature evidence exist', () => {
    render(<PodCapture deliveryId={42} />);
    const submit = screen.getByRole('button', { name: /kirim bukti/i });
    const photo = new File(['photo'], 'pod.jpg', { type: 'image/jpeg' });

    expect(submit).toBeDisabled();
    fireEvent.change(screen.getByLabelText(/foto bukti/i), { target: { files: [photo] } });
    expect(submit).toBeDisabled();

    drawSignature();
    expect(submit).toBeEnabled();
  });
});
