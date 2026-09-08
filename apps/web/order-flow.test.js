describe('order vertical slice API contract', () => {
  it('onboards, submits an idempotent order, approves it, and tracks status', async () => {
    const responses = [
      { data: { token: 'outlet-token', user: { role: 'outlet' } } },
      { data: [{ id: 7, name: 'Rice', price: '10000', stock_quantity: 4, is_active: true }] },
      { data: { id: 11, order_id: 'ORD-11', status: 'New' } },
      { data: [{ id: 11, order_id: 'ORD-11', status: 'New' }] },
      { data: { id: 11, order_id: 'ORD-11', status: 'Confirmed' } },
      { data: { id: 11, order_id: 'ORD-11', status: 'Confirmed', status_history: [{ status: 'New' }, { status: 'Confirmed' }] } },
    ];
    const fetchMock = jest.fn(async (_url, options = {}) => ({ ok: true, json: async () => responses.shift(), options }));
    global.fetch = fetchMock;

    const registration = await fetchMock('/api/auth/register', { method: 'POST', body: JSON.stringify({ email: 'outlet@example.com' }) });
    const token = (await registration.json()).data.token;
    const products = await fetchMock('/api/products', { headers: { Authorization: `Bearer ${token}` } });
    expect((await products.json()).data).toHaveLength(1);
    const idempotencyKey = 'browser-generated-idempotency-key';
    const order = await fetchMock('/api/orders', {
      method: 'POST',
      headers: { Authorization: `Bearer ${token}`, 'Idempotency-Key': idempotencyKey },
      body: JSON.stringify({ items: [{ product_id: 7, quantity: 1 }], idempotency_key: idempotencyKey }),
    });
    const orderResult = await order.json();
    expect(orderResult).toEqual({ data: { id: 11, order_id: 'ORD-11', status: 'New' } });
    expect(fetchMock.mock.calls[2][1].headers['Idempotency-Key']).toBe(idempotencyKey);
    const adminOrders = await fetchMock('/api/admin/orders', { headers: { Authorization: 'Bearer admin-token' } });
    expect((await adminOrders.json()).data).toHaveLength(1);
    const approval = await fetchMock('/api/orders/11/approve', { method: 'PUT', headers: { Authorization: 'Bearer admin-token' } });
    expect((await approval.json()).data.status).toBe('Confirmed');
    const tracking = await fetchMock('/api/orders/11', { headers: { Authorization: `Bearer ${token}` } });
    expect((await tracking.json()).data.status_history).toHaveLength(2);
    expect(fetchMock).toHaveBeenCalledTimes(6);
  });
});
