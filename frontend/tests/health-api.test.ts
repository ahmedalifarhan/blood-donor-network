import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import { fetchHealth, HEALTH_TIMEOUT_MS } from "@/lib/api/health";

// research R10: anything other than a valid 200 body is "unavailable"; 8 s timeout.

function respond(status: number, body: unknown) {
  return Promise.resolve(new Response(JSON.stringify(body), { status }));
}

describe("fetchHealth", () => {
  beforeEach(() => {
    vi.stubEnv("NEXT_PUBLIC_API_BASE_URL", "https://api.example");
  });

  afterEach(() => {
    vi.unstubAllEnvs();
    vi.unstubAllGlobals();
    vi.useRealTimers();
  });

  it("calls the public health endpoint", async () => {
    const fetchMock = vi.fn(() => respond(200, { status: "running", checkedAt: "x" }));
    vi.stubGlobal("fetch", fetchMock);

    await fetchHealth();

    expect(fetchMock).toHaveBeenCalledWith(
      "https://api.example/api/v1/health",
      expect.objectContaining({ headers: { Accept: "application/json" } }),
    );
  });

  it.each([
    [200, { status: "running", checkedAt: "x" }, "running"],
    [200, { status: "limited", checkedAt: "x" }, "limited"],
    [200, { status: "something-else" }, "unavailable"],
    [200, "not json object", "unavailable"],
    [500, { status: "running" }, "unavailable"],
    [429, {}, "unavailable"],
  ] as const)("maps HTTP %i %j to %s", async (status, body, expected) => {
    vi.stubGlobal("fetch", () => respond(status, body));

    expect(await fetchHealth()).toBe(expected);
  });

  it("returns unavailable on a network error", async () => {
    vi.stubGlobal("fetch", () => Promise.reject(new TypeError("Failed to fetch")));

    expect(await fetchHealth()).toBe("unavailable");
  });

  it("gives up after 8 seconds", async () => {
    vi.useFakeTimers();
    vi.stubGlobal(
      "fetch",
      (_url: string, init: RequestInit) =>
        new Promise((_resolve, reject) => {
          init.signal?.addEventListener("abort", () =>
            reject(new DOMException("Aborted", "AbortError")),
          );
        }),
    );

    const result = fetchHealth();
    await vi.advanceTimersByTimeAsync(HEALTH_TIMEOUT_MS);

    expect(HEALTH_TIMEOUT_MS).toBe(8000);
    expect(await result).toBe("unavailable");
  });
});
