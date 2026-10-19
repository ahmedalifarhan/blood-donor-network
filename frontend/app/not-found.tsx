import Link from "next/link";
import { useTranslations } from "next-intl";

// Every string comes from the message files (constitution IV). Next.js adds
// <meta name="robots" content="noindex"> to 404 responses automatically.
export default function NotFound() {
  const t = useTranslations("notFound");

  return (
    <main className="mx-auto flex w-full max-w-2xl flex-1 flex-col items-center justify-center gap-6 px-4 py-16 text-center">
      <h1 className="text-3xl leading-tight font-semibold">{t("title")}</h1>
      <p className="text-lg text-muted-foreground">{t("body")}</p>
      <Link
        href="/"
        className="inline-flex min-h-11 items-center rounded-lg px-4 text-base font-semibold text-foreground underline underline-offset-4 focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
      >
        {t("home")}
      </Link>
    </main>
  );
}
