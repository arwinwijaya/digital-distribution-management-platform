'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { uploadProof, type ProofResult } from '@/app/delivery/api';
import { Button, Input } from '@/components/ui';
import { useDummyStore } from '@/dummy/store';

const CANVAS_WIDTH = 600;
const CANVAS_HEIGHT = 200;

interface Props {
  deliveryId: number;
  onSuccess?: (result: ProofResult) => void;
}

export default function PodCapture({ deliveryId, onSuccess }: Props) {
  const { isDummy } = useDummyStore();
  const [photoFile, setPhotoFile] = useState<File | null>(null);
  const [photoPreview, setPhotoPreview] = useState<string | null>(null);
  const [hasSignature, setHasSignature] = useState(false);
  const [drawing, setDrawing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const canvasRef = useRef<HTMLCanvasElement>(null);
  const ctxRef = useRef<CanvasRenderingContext2D | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  /* ---- Canvas lifecycle ---- */
  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;

    /* Size the canvas once (match attributes so toBlob encodes at 1:1). */
    canvas.width = CANVAS_WIDTH;
    canvas.height = CANVAS_HEIGHT;

    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    ctxRef.current = ctx;

    ctx.strokeStyle = '#111827';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    /* Clear on mount to ensure clean state. */
    ctx.clearRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);
  }, []);

  /* ---- Drawing handlers ---- */
  const getPoint = useCallback(
    (e: React.MouseEvent<HTMLCanvasElement> | React.TouchEvent<HTMLCanvasElement>) => {
      const canvas = canvasRef.current;
      if (!canvas) return { x: 0, y: 0 };
      const rect = canvas.getBoundingClientRect();
      if ('touches' in e) {
        const touch = e.touches[0] ?? e.changedTouches[0];
        return { x: touch.clientX - rect.left, y: touch.clientY - rect.top };
      }
      return { x: e.clientX - rect.left, y: e.clientY - rect.top };
    },
    [],
  );

  const startDrawing = useCallback(
    (e: React.MouseEvent<HTMLCanvasElement> | React.TouchEvent<HTMLCanvasElement>) => {
      const ctx = ctxRef.current;
      if (!ctx) return;
      const { x, y } = getPoint(e);
      ctx.beginPath();
      ctx.moveTo(x, y);
      setDrawing(true);
      setHasSignature(true);
    },
    [getPoint],
  );

  const draw = useCallback(
    (e: React.MouseEvent<HTMLCanvasElement> | React.TouchEvent<HTMLCanvasElement>) => {
      if (!drawing) return;
      const ctx = ctxRef.current;
      if (!ctx) return;
      const { x, y } = getPoint(e);
      ctx.lineTo(x, y);
      ctx.stroke();
    },
    [drawing, getPoint],
  );

  const stopDrawing = useCallback(() => {
    setDrawing(false);
  }, []);

  const clearCanvas = useCallback(() => {
    const canvas = canvasRef.current;
    const ctx = ctxRef.current;
    if (canvas && ctx) {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
    }
    setHasSignature(false);
  }, []);

  /* ---- Photo handling ---- */
  const handlePhotoChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      const file = e.target.files?.[0] ?? null;
      setPhotoFile(file);
      if (file) {
        setPhotoPreview(URL.createObjectURL(file));
      } else {
        setPhotoPreview(null);
      }
      setError(null);
    },
    [],
  );

  /* ---- Submit ---- */
  const handleSubmit = useCallback(async () => {
    if (!photoFile || !hasSignature) return;
    setError(null);
    setSubmitting(true);

    const canvas = canvasRef.current;
    if (!canvas) {
      setError('Canvas tidak tersedia.');
      setSubmitting(false);
      return;
    }

    /* Extract signature as a PNG blob. */
    const signatureBlob: Blob | null = await new Promise((resolve) => {
      canvas.toBlob(resolve, 'image/png');
    });

    if (!signatureBlob) {
      setError('Gagal menyiapkan tanda tangan.');
      setSubmitting(false);
      return;
    }

    const formData = new FormData();
    formData.append('photo', photoFile);
    formData.append('signature', new File([signatureBlob], 'signature.png', { type: 'image/png' }));

    try {
      const result = await uploadProof(deliveryId, formData);
      if (onSuccess) onSuccess(result);

      /* Reset form on success. */
      setPhotoFile(null);
      setPhotoPreview(null);
      clearCanvas();
      if (fileInputRef.current) fileInputRef.current.value = '';
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Unggahan bukti gagal.');
    } finally {
      setSubmitting(false);
    }
  }, [deliveryId, photoFile, hasSignature, onSuccess, clearCanvas]);

  const canSubmit = photoFile !== null && hasSignature;

  return (
    <div className="space-y-4">
      {/* Photo */}
      <div>
        <Input
          ref={fileInputRef}
          type="file"
          accept="image/*"
          capture="environment"
          aria-label="Foto bukti pengiriman"
          onChange={handlePhotoChange}
          disabled={submitting}
        />
        {photoPreview && (
          <img
            src={photoPreview}
            alt="Pratinjau foto bukti pengiriman"
            className="mt-2 max-h-48 rounded-lg border border-gray-200"
          />
        )}
      </div>

      {/* Signature */}
      <div className="space-y-2">
        <label htmlFor="pod-signature" className="block text-sm font-medium text-gray-700">
          Tanda tangan penerima
        </label>
        <div className="relative rounded-lg border border-gray-200 bg-white overflow-hidden">
          <canvas
            ref={canvasRef}
            id="pod-signature"
            data-testid="pod-signature-canvas"
            className="w-full h-[200px] cursor-crosshair touch-none"
            onMouseDown={startDrawing}
            onMouseMove={draw}
            onMouseUp={stopDrawing}
            onMouseLeave={stopDrawing}
            onTouchStart={startDrawing}
            onTouchMove={draw}
            onTouchEnd={stopDrawing}
          />
        </div>
        <div className="flex items-center gap-2">
          <Button
            variant="ghost"
            size="sm"
            onClick={clearCanvas}
            disabled={!hasSignature || submitting}
          >
            Hapus tanda tangan
          </Button>
          <span className="text-xs text-gray-500">
            {hasSignature ? 'Tanda tangan tercatat' : 'Gambar tanda tangan di atas'}
          </span>
        </div>
      </div>

      {/* Error */}
      {error && (
        <p role="alert" className="text-sm text-danger-700">
          {error}
        </p>
      )}

      {/* Submit */}
      <Button
        className="w-full"
        onClick={handleSubmit}
        disabled={!canSubmit || submitting}
      >
        {submitting ? 'Mengunggah...' : 'Kirim bukti'}
      </Button>

      {/* Dummy badge */}
      {isDummy && (
        <p className="text-xs text-gray-500 text-center" data-testid="dummy-badge">
          Mode dummy aktif — unggahan disimulasikan.
        </p>
      )}
    </div>
  );
}