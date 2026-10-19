import { getRequestConfig } from "next-intl/server";

import { LOCALE } from "./config";

// next-intl without i18n routing (research R11): a fixed locale, no middleware,
// and no request-time APIs, so every page can be rendered statically.
export default getRequestConfig(async () => ({
  locale: LOCALE,
  timeZone: "UTC",
  messages: (await import(`../messages/${LOCALE}.json`)).default,
}));
