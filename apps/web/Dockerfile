FROM node:20-alpine AS base

# ---- deps ----
# Monorepo: root lockfile lives at package-lock.json (npm workspaces)
# apps/web/package.json must sit at its workspace path so npm ci resolves it.
FROM base AS deps
WORKDIR /app

# root workspace manifest + lockfile (npm ci needs both)
COPY package.json package-lock.json* ./

# the web workspace manifest at its monorepo-relative path
COPY apps/web/package.json apps/web/package.json

# --ignore-scripts keeps it fast; --workspaces covers transitive workspace deps
RUN npm ci --ignore-scripts

# ---- builder ----
FROM base AS builder
WORKDIR /app

COPY --from=deps /app/node_modules ./node_modules
COPY apps/web .

ENV NEXT_TELEMETRY_DISABLED 1

RUN npm run build

# ---- runner ----
FROM base AS runner
WORKDIR /app

ENV NODE_ENV production
ENV NEXT_TELEMETRY_DISABLED 1

RUN addgroup --system --gid 1001 nodejs
RUN adduser --system --uid 1001 nextjs

COPY --from=builder /app/public ./public

RUN mkdir .next
RUN chown nextjs:nodejs .next

COPY --from=builder --chown=nextjs:nodejs /app/.next/standalone ./
COPY --from=builder --chown=nextjs:nodejs /app/.next/static ./.next/static

USER nextjs

EXPOSE 3000

ENV PORT 3000
ENV HOSTNAME "0.0.0.0"

CMD ["node", "server.js"]
