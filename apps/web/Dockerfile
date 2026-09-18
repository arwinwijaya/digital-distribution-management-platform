FROM node:20-alpine

WORKDIR /app

# Root workspace manifest + lockfile
COPY package.json package-lock.json* ./

# Workspace manifests — the lockfile resolves the whole monorepo, so every
# workspace package.json must be present or `npm ci` fails/desyncs.
COPY apps/web/package.json apps/web/package.json
COPY packages/shared/package.json packages/shared/package.json

RUN npm ci

# Copy sources (.dockerignore keeps host node_modules/.next out of the context,
# so the dependencies installed above are never clobbered by Windows builds).
COPY apps/web ./apps/web
COPY packages/shared ./packages/shared

ENV NEXT_TELEMETRY_DISABLED=1
ENV NEXT_PUBLIC_API_URL=http://localhost:8000/api
ENV PORT=3000
ENV HOSTNAME="0.0.0.0"

EXPOSE 3000

WORKDIR /app/apps/web

CMD ["npm", "run", "dev"]
