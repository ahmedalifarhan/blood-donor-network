// Typed client for GET /api/v1/health (contracts/http-api.yaml, research R10).

export type HealthState = "running" | "limited" | "unavailable";

export const HEALTH_TIMEOUT_MS = 8000;

type PublicHealth = { status: "running" | "limited"; checkedAt: string };

function isPublicHealth(body: unknown): body is PublicHealth {
  return (
    typeof body === "object" &&
    body !== null &&
    "status" in body &&
    (body.status === "running" || body.status === "limited")
  );
}

/**
 * Never throws: any non-200 response, invalid body, network error, abort, or a
 * wait longer than 8 seconds (e.g. the free backend waking up) is "unavailable".
 */
export async function fetchHealth(signal?: AbortSignal): Promise<HealthState> {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), HEALTH_TIMEOUT_MS);
  const forwardAbort = () => controller.abort();
  signal?.addEventListener("abort", forwardAbort);

  try {
    const baseUrl = process.env.NEXT_PUBLIC_API_BASE_URL ?? "";
    const response = await fetch(`${baseUrl}/api/v1/health`, {
      headers: { Accept: "application/json" },
      cache: "no-store",
      signal: controller.signal,
    });

    if (response.status !== 200) {
      return "unavailable";
    }

    const body: unknown = await response.json();

    return isPublicHealth(body) ? body.status : "unavailable";
  } catch {
    return "unavailable";
  } finally {
    clearTimeout(timer);
    signal?.removeEventListener("abort", forwardAbort);
  }
}
