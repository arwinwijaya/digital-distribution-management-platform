'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import { apiUrl, authHeaders, getStoredToken } from '@/lib/api';
import { fetchAdminDrivers, createDriver, updateDriver, deleteDriver, type AdminDriver, type DriverFilters } from './api';
import { Button, Card, EmptyState, PageHeader, StatusBadge, Table, TablePagination, TableSummary, TableDensityToggle } from '@/components/ui';
import { formatDateTime, toggleSort, type ColumnSort } from '@/lib/admin-table';
import { useTableDensity } from '@/hooks/useTableDensity';
import Modal from '@/components/ui/Modal';
import { Input, Select } from '@/components/ui';

const PAGE_LIMIT = 15;

type DriverFormData = {
  user_id: string;
  vehicle_type: string;
  plate_number: string;
  capacity_kg: string;
  service_territory_id: string;
  shift_start: string;
  shift_end: string;
  is_available: boolean;
};

export default function AdminDriversPage() {
  const [token, setToken] = useState<string | null>(null);
  const [drivers, setDrivers] = useState<AdminDriver[]>([]);
  const [selected, setSelected] = useState<AdminDriver | null>(null);
  const [ready, setReady] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  // Table state
  const [sort, setSort] = useState<ColumnSort>({ column: 'created_at', order: 'desc' });
  const [cursor, setCursor] = useState(0);
  const [total, setTotal] = useState<number | undefined>(undefined);
  const [hasMore, setHasMore] = useState(false);
  const [search, setSearch] = useState('');
  const [isAvailableFilter, setIsAvailableFilter] = useState<string>('');
  const { density, setDensity } = useTableDensity();
  // Modal state
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingDriver, setEditingDriver] = useState<AdminDriver | null>(null);
  const [formData, setFormData] = useState<DriverFormData>({
    user_id: '',
    vehicle_type: '',
    plate_number: '',
    capacity_kg: '',
    service_territory_id: '',
    shift_start: '',
    shift_end: '',
    is_available: true,
  });
  const [formErrors, setFormErrors] = useState<Partial<Record<keyof DriverFormData, string>>>({});
  const [submitting, setSubmitting] = useState(false);

  // Refs for latest sort/cursor without adding to dependency list
  const sortRef = useRef(sort);
  sortRef.current = sort;
  const cursorRef = useRef(cursor);
  cursorRef.current = cursor;

  const loadDrivers = useCallback(async (authToken: string, opts?: { resetCursor?: boolean; cursor?: number; sort?: ColumnSort }) => {
    setLoading(true);
    setError(null);
    try {
      const nextSort = opts?.sort ?? sortRef.current;
      const nextCursor = opts?.cursor ?? (opts?.resetCursor ? 0 : cursorRef.current);
      const filters: DriverFilters = {
        limit: PAGE_LIMIT,
        cursor: nextCursor,
        sort: nextSort.column,
        order: nextSort.order,
        search: search || undefined,
        is_available: isAvailableFilter || undefined,
      };
      const result = await fetchAdminDrivers(authToken, filters);
      setDrivers(result.drivers);
      setHasMore(result.hasMore);
      setCursor(result.cursor);
      if (result.total !== undefined) setTotal(result.total);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Daftar driver tidak dapat dimuat.');
    } finally {
      setLoading(false);
    }
  }, [search, isAvailableFilter]);

  useEffect(() => {
    const stored = getStoredToken();
    setToken(stored);
    setReady(true);
    if (stored) loadDrivers(stored, { resetCursor: true });
  }, [loadDrivers]);

  function openCreateModal() {
    setEditingDriver(null);
    setFormData({
      user_id: '',
      vehicle_type: '',
      plate_number: '',
      capacity_kg: '',
      service_territory_id: '',
      shift_start: '',
      shift_end: '',
      is_available: true,
    });
    setFormErrors({});
    setIsModalOpen(true);
  }

  function openEditModal(driver: AdminDriver) {
    setEditingDriver(driver);
    setFormData({
      user_id: String(driver.id),
      vehicle_type: driver.vehicle_type ?? '',
      plate_number: driver.plate_number ?? '',
      capacity_kg: driver.capacity_kg?.toString() ?? '',
      service_territory_id: driver.service_territory_id?.toString() ?? '',
      shift_start: driver.shift_start ?? '',
      shift_end: driver.shift_end ?? '',
      is_available: driver.is_available,
    });
    setFormErrors({});
    setIsModalOpen(true);
  }

  function closeModal() {
    setIsModalOpen(false);
    setEditingDriver(null);
    setFormErrors({});
  }

  function handleFormChange(e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) {
    const { name, value, type } = e.target;
    const checked = type === 'checkbox' ? (e.target as HTMLInputElement).checked : undefined;
    setFormData((prev) => ({ ...prev, [name]: checked ?? value }));
    if (formErrors[name as keyof DriverFormData]) {
      setFormErrors((prev) => ({ ...prev, [name]: undefined }));
    }
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setFormErrors({});
    const errors: Partial<Record<keyof DriverFormData, string>> = {};

    if (!formData.user_id) errors.user_id = 'User ID wajib diisi.';
    if (!formData.vehicle_type) errors.vehicle_type = 'Jenis kendaraan wajib diisi.';
    if (!formData.plate_number) errors.plate_number = 'Nomor plat wajib diisi.';

    if (Object.keys(errors).length > 0) {
      setFormErrors(errors);
      return;
    }

    setSubmitting(true);
    try {
      const payload = {
        user_id: formData.user_id,
        vehicle_type: formData.vehicle_type || undefined,
        plate_number: formData.plate_number || undefined,
        capacity_kg: formData.capacity_kg ? Number(formData.capacity_kg) : undefined,
        service_territory_id: formData.service_territory_id ? Number(formData.service_territory_id) : undefined,
        shift_start: formData.shift_start || undefined,
        shift_end: formData.shift_end || undefined,
        is_available: formData.is_available,
      };

      if (editingDriver) {
        await updateDriver(token!, editingDriver.id, payload);
      } else {
        await createDriver(token!, payload as typeof payload & { user_id: string });
      }
      closeModal();
      await loadDrivers(token!, { resetCursor: true });
    } catch (reason) {
      const message = reason instanceof Error ? reason.message : 'Operasi gagal.';
      // Try to parse 422 validation errors
      if (message.includes('422') || message.includes('Unprocessable')) {
        // Backend returns structured validation errors; we'll show generic for now
        setFormErrors({ user_id: message });
      } else {
        setError(message);
      }
    } finally {
      setSubmitting(false);
    }
  }

  async function handleDelete(driver: AdminDriver) {
    if (!window.confirm(`Hapus driver "${driver.name}" (plat: ${driver.plate_number ?? '-'})?`)) return;
    if (!token) return;
    try {
      await deleteDriver(token, driver.id);
      await loadDrivers(token, { resetCursor: true });
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Driver tidak dapat dihapus.');
    }
  }

  if (!ready) return <p className="text-sm text-gray-500">Memuat...</p>;
  if (!token) {
    return (
      <div className="mx-auto max-w-6xl">
        <PageHeader title="Roster Driver" description="Kelola profil driver untuk operasi lapangan." />
        <div className="mb-5 rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-700">
          Masuk sebagai administrator untuk mengelola driver.
        </div>
        <LoginForm expectedRole="admin" onLogin={(nextToken) => { setToken(nextToken); loadDrivers(nextToken); }} />
      </div>
    );
  }

  const columns = [
    {
      key: 'name',
      header: 'Nama Driver',
      render: (driver: AdminDriver) => (
        <span className="font-medium text-gray-900">{driver.name}</span>
      ),
    },
    {
      key: 'vehicle_type',
      header: 'Jenis Kendaraan',
      render: (driver: AdminDriver) => (
        <span className="text-gray-700">{driver.vehicle_type ?? '-'}</span>
      ),
    },
    {
      key: 'plate_number',
      header: 'Nomor Plat',
      render: (driver: AdminDriver) => (
        <span className="font-mono text-gray-700">{driver.plate_number ?? '-'}</span>
      ),
    },
    {
      key: 'territory',
      header: 'Wilayah',
      render: (driver: AdminDriver) => (
        <span className="text-gray-700">{driver.territory?.name ?? '-'}</span>
      ),
    },
    {
      key: 'capacity_kg',
      header: 'Kapasitas (kg)',
      render: (driver: AdminDriver) => (
        <span className="text-gray-700 text-right">{driver.capacity_kg?.toLocaleString('id-ID') ?? '-'}</span>
      ),
    },
    {
      key: 'shift',
      header: 'Shift',
      render: (driver: AdminDriver) => (
        <span className="text-gray-700">
          {driver.shift_start && driver.shift_end
            ? `${driver.shift_start} - ${driver.shift_end}`
            : '-'}
        </span>
      ),
    },
    {
      key: 'is_available',
      header: 'Status',
      render: (driver: AdminDriver) => (
        <StatusBadge status={driver.is_available ? 'active' : 'inactive'} />
      ),
    },
    {
      key: 'action',
      header: 'Aksi',
      render: (driver: AdminDriver) => (
        <div className="flex items-center gap-2">
          <Button size="sm" variant="secondary" onClick={() => openEditModal(driver)}>
            Edit
          </Button>
          <Button size="sm" variant="danger" onClick={() => handleDelete(driver)}>
            Hapus
          </Button>
        </div>
      ),
    },
  ];

  return (
    <div className="mx-auto max-w-6xl">
      <PageHeader title="Roster Driver" description="Kelola profil driver untuk operasi lapangan." />
      {error && (
        <p role="alert" className="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">
          {error}
        </p>
      )}
      <div className="mb-4 flex items-center justify-between">
        <Button onClick={openCreateModal}>Tambah Driver</Button>
        <div className="flex items-center gap-3">
          <Input
            aria-label="Cari driver"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Cari nama, plat, kendaraan..."
            className="w-64"
          />
          <Select
            value={isAvailableFilter}
            onChange={(e) => setIsAvailableFilter(e.target.value)}
            aria-label="Filter ketersediaan"
          >
            <option value="">Semua Status</option>
            <option value="true">Tersedia</option>
            <option value="false">Tidak Tersedia</option>
          </Select>
        </div>
      </div>
      <Card className="overflow-hidden">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3">
          <TableSummary
            total={total ?? drivers.length}
            noun="driver"
            breakdown={[
              { label: 'tersedia', value: drivers.filter(d => d.is_available).length },
              { label: 'tidak', value: drivers.filter(d => !d.is_available).length },
            ]}
          />
          <TableDensityToggle value={density} onChange={setDensity} />
        </div>
        {loading && drivers.length === 0 ? (
          <p className="p-8 text-sm text-gray-500">Memuat driver...</p>
        ) : (
          <Table
            columns={columns}
            rows={drivers}
            rowKey={(driver) => driver.id}
            density={density}
            sortableColumns={['created_at', 'plate_number', 'id']}
            sort={sort}
            onSort={(column) => {
              const next = toggleSort(sortRef.current, column);
              setSort(next);
              if (token) void loadDrivers(token, { resetCursor: true, sort: next });
            }}
            empty={
              <EmptyState
                icon={<span>🚚</span>}
                title="Belum ada driver"
                description="Tambahkan driver pertama untuk memulai."
              />
            }
          />
        )}
        <TablePagination
          cursor={cursor}
          limit={PAGE_LIMIT}
          total={total}
          hasMore={hasMore}
          onPageChange={(nextCursor) => {
            setCursor(nextCursor);
            if (token) void loadDrivers(token, { cursor: nextCursor });
          }}
        />
      </Card>

      {/* Create/Edit Modal */}
      <Modal
        open={isModalOpen}
        onClose={closeModal}
        title={editingDriver ? 'Edit Driver' : 'Tambah Driver'}
        footer={
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={closeModal} disabled={submitting}>
              Batal
            </Button>
            <Button onClick={async () => { await handleSubmit({ preventDefault: () => {} } as React.FormEvent); }} disabled={submitting}>
              {submitting ? 'Menyimpan...' : editingDriver ? 'Simpan Perubahan' : 'Tambah Driver'}
            </Button>
          </div>
        }
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          <Input
            label="User ID"
            name="user_id"
            type="number"
            value={formData.user_id}
            onChange={handleFormChange}
            placeholder="Masukkan User ID"
            error={formErrors.user_id}
            disabled={editingDriver !== null}
            required
          />
          <Input
            label="Jenis Kendaraan"
            name="vehicle_type"
            value={formData.vehicle_type}
            onChange={handleFormChange}
            placeholder="Contoh: Motor, Mobil Pickup, Truk"
            error={formErrors.vehicle_type}
            required
          />
          <Input
            label="Nomor Plat"
            name="plate_number"
            value={formData.plate_number}
            onChange={handleFormChange}
            placeholder="Contoh: B 1234 ABC"
            error={formErrors.plate_number}
            required
          />
          <Input
            label="Kapasitas (kg)"
            name="capacity_kg"
            type="number"
            min="0"
            value={formData.capacity_kg}
            onChange={handleFormChange}
            placeholder="Contoh: 500"
          />
          <Select
            label="Wilayah Layanan"
            name="service_territory_id"
            value={formData.service_territory_id}
            onChange={handleFormChange}
          >
            <option value="">Pilih wilayah</option>
            {JABODETABEK_TERRITORIES.map((t) => (
              <option key={t.id} value={String(JABODETABEK_TERRITORIES.indexOf(t) + 1)}>
                {t.name}
              </option>
            ))}
          </Select>
          <div className="grid gap-3 sm:grid-cols-2">
            <Input
              label="Mulai Shift"
              name="shift_start"
              type="time"
              value={formData.shift_start}
              onChange={handleFormChange}
            />
            <Input
              label="Selesai Shift"
              name="shift_end"
              type="time"
              value={formData.shift_end}
              onChange={handleFormChange}
            />
          </div>
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              name="is_available"
              checked={formData.is_available}
              onChange={(e) => handleFormChange(e as unknown as React.ChangeEvent<HTMLInputElement>)}
              className="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
            />
            <span className="text-gray-700">Driver tersedia untuk penugasan</span>
          </label>
        </form>
      </Modal>
    </div>
  );
}

// Import JABODETABEK_TERRITORIES for the modal select
import { JABODETABEK_TERRITORIES } from '@/dummy/seed';