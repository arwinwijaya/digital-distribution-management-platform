FROM node:20-alpine

WORKDIR /app

# Root workspace manifest + lockfile
COPY package.json package-lock.json* ./
COPY apps/web/package.json apps/web/package.json

RUN npm ci

# Copy web source
COPY apps/web ./apps/web

ENV NEXT_TELEMETRY_DISABLED=1
ENV NEXT_PUBLIC_API_URL=http://localhost:8000/api
ENV PORT=3000
ENV HOSTNAME="0.0.0.0"

EXPOSE 3000

WORKDIR /app/apps/web

CMD ["npm", "run", "dev"]
