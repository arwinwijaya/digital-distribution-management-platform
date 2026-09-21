# Docker Credentials Cheat Sheet

Default password for all seeded users:

```
password123
```

## Users

| Role | Email | Password | Notes |
|---|---|---|---|
| platform_owner | ahmad.wijaya@ddp.test | password123 | Platform owner |
| admin | ratna.sari@ddp.test | password123 | Admin |
| admin | dimas.pratama@ddp.test | password123 | Admin |
| outlet | siti.nurhaliza@ddp.test | password123 | Outlet user |
| outlet | budi.santoso@ddp.test | password123 | Outlet user |
| supplier | hendra.kurniawan@ddp.test | password123 | Supplier user |
| supplier | maya.indah@ddp.test | password123 | Supplier user |
| sales | riko.firmansyah@ddp.test | password123 | Sales |
| sales | anisa.putri@ddp.test | password123 | Sales |
| sales | ferry.gunawan@ddp.test | password123 | Sales |
| driver | joko.widodo@ddp.test | password123 | Driver |
| driver | andi.saputra@ddp.test | password123 | Driver |
| driver | rudi.hermawan@ddp.test | password123 | Driver |
| finance | dewi.lestari@ddp.test | password123 | Finance |
| finance | tono.sugiarto@ddp.test | password123 | Finance |

## API Login Example

```bash
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"ratna.sari@ddp.test","password":"password123"}'
```

## Dev Helper

```bash
bash scripts/login-test.sh http://localhost:8080/api password123
```

## Secrets Reminder

- Update `APP_KEY`, `JWT_SECRET`, and `DB_PASSWORD` for production.
- Do not commit real `.env`.
