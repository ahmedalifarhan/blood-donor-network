import { useTranslations } from "next-intl";
import { setRequestLocale } from "next-intl/server";

import { ServiceStatus } from "@/components/service-status";
import { LOCALE } from "@/i18n/config";

// Statically rendered shell; the status itself is fetched in the browser (research R10).
export default function Home() {
  setRequestLocale(LOCALE);
  const t = useTranslations("site");

  return (
    <main className="mx-auto flex w-full max-w-2xl flex-1 flex-col items-center justify-center gap-8 px-4 py-16 text-center">
      <h1 className="text-3xl leading-tight font-semibold">{t("name")}</h1>
      <ServiceStatus />
    </main>
  );
}
