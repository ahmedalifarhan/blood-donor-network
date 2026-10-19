import { act, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { NextIntlClientProvider } from "next-intl";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { ServiceStatus } from "@/components/service-status";
import { fetchHealth } from "@/lib/api/health";
import messages from "@/messages/ar.json";

// US1, contracts/ui-status.md: four states, live region, retry only when unavailable.

vi.mock("@/lib/api/health", () => ({ fetchHealth: vi.fn() }));
const mockedFetchHealth = vi.mocked(fetchHealth);

function renderStatus() {
  return render(
    <NextIntlClientProvider locale="ar" messages={messages}>
      <ServiceStatus />
    </NextIntlClientProvider>,
  );
}

describe("ServiceStatus", () => {
  // Block body on purpose: a function returned from beforeEach becomes a teardown.
  beforeEach(() => {
    mockedFetchHealth.mockReset();
  });

  it("shows the checking state first, in a polite live region", () => {
    mockedFetchHealth.mockReturnValue(new Promise(() => {}));
    renderStatus();

    const region = screen.getByRole("status");
    expect(region).toHaveAttribute("aria-live", "polite");
    expect(region).toHaveTextContent(messages.status.checking);
    expect(region).toHaveAttribute("data-state", "checking");
  });

  it.each([
    ["running", messages.status.running],
    ["limited", messages.status.limited],
    ["unavailable", messages.status.unavailable],
  ] as const)("shows the %s state with its text and an icon", async (state, text) => {
    mockedFetchHealth.mockResolvedValue(state);
    renderStatus();

    const region = screen.getByRole("status");
    expect(await screen.findByText(text)).toBeInTheDocument();
    expect(region).toHaveAttribute("data-state", state);
    expect(region.querySelector("svg")).not.toBeNull();
  });

  it("offers Retry only when unavailable, and Retry checks again", async () => {
    mockedFetchHealth.mockResolvedValueOnce("unavailable").mockResolvedValueOnce("running");
    renderStatus();

    const retry = await screen.findByRole("button", { name: messages.status.retry });
    await userEvent.click(retry);

    expect(await screen.findByText(messages.status.running)).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: messages.status.retry })).toBeNull();
    expect(mockedFetchHealth).toHaveBeenCalledTimes(2);
  });

  it("does not show Retry while running or limited", async () => {
    mockedFetchHealth.mockResolvedValue("limited");
    renderStatus();

    await screen.findByText(messages.status.limited);
    await act(async () => {});
    expect(screen.queryByRole("button")).toBeNull();
  });
});
