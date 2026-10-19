import { useTranslations } from "next-intl";

// FR-005: the medical disclaimer appears on every page.
export function SiteFooter() {
  const t = useTranslations("footer");

  return (
    <footer className="border-t border-border bg-muted/40">
      <p className="mx-auto max-w-2xl px-4 py-6 text-sm leading-relaxed text-muted-foreground">
        {t("disclaimer")}
      </p>
    </footer>
  );
}
