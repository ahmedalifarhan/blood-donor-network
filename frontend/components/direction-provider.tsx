"use client";

import { Direction } from "radix-ui";
import type { ReactNode } from "react";

// Radix components read text direction from context; the provider is client-only.
export function DirectionProvider({ dir, children }: { dir: "ltr" | "rtl"; children: ReactNode }) {
  return <Direction.DirectionProvider dir={dir}>{children}</Direction.DirectionProvider>;
}
