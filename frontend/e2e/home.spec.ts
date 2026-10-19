import { readFileSync } from "node:fs";
import { join } from "node:path";
import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page, type Route } from "@playwright/test";

// US1 end-to-end: contracts/ui-status.md, SC-001, SC-005, SC-010.

// Playwright compiles specs as CommonJS here, so __dirname is available.
const messages = JSON.parse(readFileSync(join(__dirname, "../messages/ar.json"), "utf8")) as {
  site: { name: string };
  status: Record<"checking" | "running" | "limited" | "unavailable" | "retry", string>;
  notFound: { title: string };
  footer: { disclaimer: string };
};

const HEALTH = "**/api/v1/health";
const WCAG_TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa"];

function healthResponse(status: "running" | "limited") {
  return (route: Route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      headers: { "Access-Control-Allow-Origin": "*" },
      body: JSON.stringify({ status, checkedAt: "2026-10-19T10:15:00Z" }),
    });
}

async function expectAccessible(page: Page) {
  const results = await new AxeBuilder({ page }).withTags(WCAG_TAGS).analyze();
  expect(results.violations).toEqual([]);
}

async function expectPageShell(page: Page) {
  await expect(page.locator("html")).toHaveAttribute("lang", "ar");
  await expect(page.locator("html")).toHaveAttribute("dir", "rtl");
  // Crawlers combine every robots tag; a 404 page also gets Next.js's own "noindex" tag.
  const robots = (
    await page
      .locator('meta[name="robots"]')
      .evaluateAll((tags) => tags.map((tag) => tag.getAttribute("content") ?? ""))
  ).join(",");
  expect(robots).toContain("noindex");
  expect(robots).toContain("nofollow");
  await expect(page.getByRole("contentinfo")).toContainText(messages.footer.disclaimer);
}

test.describe("home status page", () => {
  test("running: Arabic RTL shell, disclaimer, noindex, accessible", async ({ page }) => {
    await page.route(HEALTH, healthResponse("running"));
    await page.goto("/");

    await expect(page.getByRole("heading", { level: 1 })).toHaveText(messages.site.name);
    await expect(page.getByRole("status")).toHaveText(messages.status.running);
    await expectPageShell(page);
    await expectAccessible(page);
  });

  test("limited: calm message, no technical detail", async ({ page }) => {
    await page.route(HEALTH, healthResponse("limited"));
    await page.goto("/");

    const status = page.getByRole("status");
    await expect(status).toHaveText(messages.status.limited);
    await expect(page.locator("body")).not.toContainText(/error|exception|\b5\d\d\b/i);
    await expectAccessible(page);
  });

  test("HTTP 500 → unavailable with a Retry button", async ({ page }) => {
    await page.route(HEALTH, (route) =>
      route.fulfill({ status: 500, headers: { "Access-Control-Allow-Origin": "*" }, body: "" }),
    );
    await page.goto("/");

    await expect(page.getByRole("status")).toContainText(messages.status.unavailable);
    await expect(page.getByRole("button", { name: messages.status.retry })).toBeVisible();
    await expectAccessible(page);
  });

  test("aborted request → unavailable", async ({ page }) => {
    await page.route(HEALTH, (route) => route.abort("failed"));
    await page.goto("/");

    await expect(page.getByRole("status")).toContainText(messages.status.unavailable);
  });

  test("fits a 360 px screen and every button is at least 44×44 px", async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 740 });
    await page.route(HEALTH, (route) => route.abort("failed"));
    await page.goto("/");
    await expect(page.getByRole("button", { name: messages.status.retry })).toBeVisible();

    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
    );
    expect(overflow).toBeLessThanOrEqual(0);

    for (const button of await page.getByRole("button").all()) {
      const box = await button.boundingBox();
      expect(box?.width ?? 0).toBeGreaterThanOrEqual(44);
      expect(box?.height ?? 0).toBeGreaterThanOrEqual(44);
    }
  });

  test("SC-001: readable within 5 s on a slow mobile connection", async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    const cdp = await context.newCDPSession(page);
    await cdp.send("Network.enable");
    await cdp.send("Network.setCacheDisabled", { cacheDisabled: true });
    await cdp.send("Network.emulateNetworkConditions", {
      offline: false,
      latency: 150,
      downloadThroughput: (1.6 * 1024 * 1024) / 8,
      uploadThroughput: (750 * 1024) / 8,
    });
    await cdp.send("Emulation.setCPUThrottlingRate", { rate: 4 });

    const started = Date.now();
    await page.goto("/", { waitUntil: "commit" });
    await expect(page.getByRole("heading", { level: 1 })).toBeVisible({ timeout: 5000 });
    await expect(page.getByRole("contentinfo")).toBeVisible({ timeout: 5000 });
    expect(Date.now() - started).toBeLessThan(5000);

    await context.close();
  });
});

test.describe("404 page", () => {
  test("has the same shell, disclaimer, and noindex", async ({ page }) => {
    const response = await page.goto("/this-page-does-not-exist");

    expect(response?.status()).toBe(404);
    await expect(page.getByRole("heading", { level: 1 })).toHaveText(messages.notFound.title);
    await expectPageShell(page);
    await expectAccessible(page);
  });
});
