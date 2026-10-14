'use client';

import { useEffect, useRef, useState } from 'react';
import { createOrderQueue, createDefaultAdapter, type OrderQueue } from '@/lib/offline/order-queue';
import { apiUrl, authHeaders } from '@/lib/api';
import { invalidateOutletOrdersCache } from '@/app/dashboard/api';

export function useOrderQueue(token: string, isOnline: boolean, isDummy: boolean) {
  const queueRef = useRef<OrderQueue | null>(null);
  const [pendingCount, setPendingCount] = useState(0);

  useEffect(() => {
    let mounted = true;
    createDefaultAdapter().then((adapter) => {
      if (mounted) queueRef.current = createOrderQueue(adapter);
    });
    return () => { mounted = false; };
  }, []);

  useEffect(() => {
    if (queueRef.current) {
      queueRef.current.list().then((items) => setPendingCount(items.length));
    }
  }, [isOnline]);

  useEffect(() => {
    if (!isOnline || !queueRef.current || isDummy) return;
    const flush = async () => {
      const result = await queueRef.current!.flushQueue(async (payload, init) => {
        const response = await fetch(apiUrl('/orders'), {
          method: 'POST',
          headers: { ...authHeaders(token), ...(init?.headers as Record<string, string>) },
          body: JSON.stringify(payload),
        });
        return response;
      });
      if (result.sent > 0) invalidateOutletOrdersCache(token);
      if (result.sent > 0 || result.failed > 0) {
        setPendingCount(result.failed);
      }
    };
    flush();
  }, [isOnline, token, isDummy]);

  return { queueRef, pendingCount, setPendingCount };
}