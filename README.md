# BACK-SYS-CONTA-HME

Backend del sistema contable HME.

## Requisitos

- Node.js 20+
- pnpm 9+

## Instalacion

```powershell
pnpm install
Copy-Item .env.example .env
pnpm dev
```

La API escucha por defecto en `http://localhost:5055`.

## Scripts

```powershell
pnpm dev
pnpm build
pnpm typecheck
pnpm lint
```

## Variables de entorno

- `PORT`: puerto HTTP del backend.
- `ADMIN_PASSWORD`: clave temporal del usuario `admin`.
