import type { Metadata } from "next";
import { IBM_Plex_Sans_Arabic } from "next/font/google";
import { NextIntlClientProvider } from "next-intl";
import { getTranslations, setRequestLocale } from "next-intl/server";

import { DirectionProvider } from "@/components/direction-provider";
import { SiteFooter } from "@/components/site-footer";
import { DIRECTION, LOCALE } from "@/i18n/config";
import "./globals.css";

// Downloaded at build time and self-hosted: visitors never contact Google (research R11).
const arabic = IBM_Plex_Sans_Arabic({
  subsets: ["arabic"],
  weight: ["400", "600"],
  display: "swap",
  variable: "--font-arabic",
});

export async function generateMetadata(): Promise<Metadata> {
  setRequestLocale(LOCALE);
  const t = await getTranslations({ locale: LOCALE, namespace: "site" });

  return {
    title: t("name"),
    // FR-006: hidden from search engines unless a later feature opts a page in.
    robots: { index: false, follow: false },
  };
}

export default function RootLayout({ children }: LayoutProps<"/">) {
  setRequestLocale(LOCALE);

  return (
    <html lang={LOCALE} dir={DIRECTION} className={`${arabic.variable} h-full antialiased`}>
      <body className="flex min-h-full flex-col">
        <NextIntlClientProvider>
          <DirectionProvider dir={DIRECTION}>
            <div className="flex flex-1 flex-col">{children}</div>
            <SiteFooter />
          </DirectionProvider>
        </NextIntlClientProvider>
      </body>
    </html>
  );
}
