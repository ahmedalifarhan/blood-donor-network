import { join } from "node:path";
import { defineConfig, devices } from "@playwright/test";

const API_URL = "http://localhost:8000";
const WEB_URL = "http://localhost:3000";
const isCI = Boolean(process.env.CI);
// A throwaway SQLite file, rebuilt by migrate:fresh on every run (git-ignored).
const E2E_DATABASE = join(__dirname, "../backend/database/e2e.sqlite");

// Test-only values. Never real secrets (constitution VII).
const BACKEND_ENV = {
  APP_ENV: "local",
  APP_KEY: "base64:ZTJlLW9ubHktYXBwLWtleS1ub3QtYS1zZWNyZXQhIQ==",
  DB_CONNECTION: "sqlite",
  DB_DATABASE: E2E_DATABASE,
  // Blank, so a PostgreSQL URL in backend/.env can never reach migrate:fresh below.
  DB_URL: "",
  CACHE_STORE: "file",
  SESSION_DRIVER: "array",
  LOG_CHANNEL: "stderr",
  SCHEDULER_SECRET: "e2e-scheduler-secret",
  OPERATOR_HEALTH_SECRET: "e2e-operator-secret",
  FRONTEND_URL: WEB_URL,
};

export default defineConfig({
  testDir: "./e2e",
  fullyParallel: true,
  forbidOnly: isCI,
  retries: isCI ? 1 : 0,
  reporter: isCI ? [["list"], ["html", { open: "never" }]] : "list",
  use: {
    baseURL: WEB_URL,
    trace: "retain-on-failure",
  },
  projects: [
    {
      // Mobile-first: a small Android-sized viewport (contracts/ui-status.md).
      name: "mobile-chromium",
      use: { ...devices["Desktop Chrome"], viewport: { width: 360, height: 740 } },
    },
  ],
  webServer: [
    {
      // Backend on a fresh, migrated SQLite database.
      command:
        "php -r \"touch('database/e2e.sqlite');\" && php artisan migrate:fresh --force && php artisan serve --port=8000",
      cwd: "../backend",
      url: `${API_URL}/up`,
      env: BACKEND_ENV,
      reuseExistingServer: !isCI,
      timeout: 120_000,
    },
    {
      // Production build, as served by Vercel.
      command: "npm run build && npm run start -- --port 3000",
      url: WEB_URL,
      env: { NEXT_PUBLIC_API_BASE_URL: API_URL },
      reuseExistingServer: !isCI,
      timeout: 240_000,
    },
  ],
});
