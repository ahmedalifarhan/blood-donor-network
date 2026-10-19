"use client";

import { CircleCheck, CircleX, LoaderCircle, RotateCw, TriangleAlert } from "lucide-react";
import { useTranslations } from "next-intl";
import { useEffect, useState } from "react";

import { Button } from "@/components/ui/button";
import { fetchHealth, type HealthState } from "@/lib/api/health";
import { cn } from "@/lib/utils";

type ViewState = "checking" | HealthState;

// Every state has an icon and text, so color is never the only signal (WCAG 1.4.1).
const VIEW = {
  checking: { Icon: LoaderCircle, tone: "border-border bg-muted text-foreground" },
  running: {
    Icon: CircleCheck,
    tone: "border-status-running bg-status-running-bg text-status-running",
  },
  limited: {
    Icon: TriangleAlert,
    tone: "border-status-limited bg-status-limited-bg text-status-limited",
  },
  unavailable: {
    Icon: CircleX,
    tone: "border-status-unavailable bg-status-unavailable-bg text-status-unavailable",
  },
} as const;

/** Live service status from the backend (US1, contracts/ui-status.md). */
export function ServiceStatus() {
  const t = useTranslations("status");
  const [state, setState] = useState<ViewState>("checking");
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    let active = true;
    void fetchHealth().then((result) => {
      if (active) setState(result);
    });

    return () => {
      active = false;
    };
  }, [attempt]);

  const retry = () => {
    setState("checking");
    setAttempt((n) => n + 1);
  };

  const { Icon, tone } = VIEW[state];

  return (
    <div className="flex w-full flex-col items-center gap-4">
      <p
        role="status"
        aria-live="polite"
        data-state={state}
        className={cn(
          "flex w-full max-w-md items-center justify-center gap-3 rounded-xl border-2 px-5 py-4 text-lg font-semibold",
          tone,
        )}
      >
        <Icon
          aria-hidden="true"
          className={cn("size-6 shrink-0", state === "checking" && "motion-safe:animate-spin")}
        />
        <span>{t(state)}</span>
      </p>

      {state === "unavailable" && (
        <Button
          type="button"
          size="lg"
          onClick={retry}
          className="min-h-11 min-w-11 px-5 text-base"
        >
          <RotateCw aria-hidden="true" />
          {t("retry")}
        </Button>
      )}
    </div>
  );
}
