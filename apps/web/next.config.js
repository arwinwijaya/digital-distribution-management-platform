const path = require('path');

/** @type {import('next').NextConfig} */
const nextConfig = {
  output: 'standalone',
  // Required in a monorepo: without it Next traces from apps/web and the
  // standalone bundle omits hoisted root node_modules, so `server.js` crashes
  // at runtime with "Cannot find module". Traces from the repo root instead.
  outputFileTracingRoot: path.join(__dirname, '../../'),
  reactStrictMode: true,
  images: {
    domains: ['localhost'],
  },
  env: {
    NEXT_PUBLIC_API_URL: process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8080/api',
  },
};

module.exports = nextConfig;
