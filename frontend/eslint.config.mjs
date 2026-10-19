import { defineConfig, globalIgnores } from "eslint/config";
import nextVitals from "eslint-config-next/core-web-vitals";
import nextTs from "eslint-config-next/typescript";
import jsxA11y from "eslint-plugin-jsx-a11y";

// Physical left/right utilities break right-to-left layouts (constitution IV).
// Use the logical ones instead: ms-/me-/ps-/pe-/start-/end-.
const PHYSICAL_CLASS = String.raw`/(^|[\s:])-?(ml|mr|pl|pr|left|right)-/`;
const PHYSICAL_CLASS_MESSAGE =
  "Use logical utilities (ms-/me-/ps-/pe-/start-/end-) instead of physical left/right ones.";

const eslintConfig = defineConfig([
  ...nextVitals,
  ...nextTs,
  {
    // eslint-config-next already registers the jsx-a11y plugin; enable its full recommended rules.
    rules: {
      ...jsxA11y.flatConfigs.recommended.rules,
      "no-restricted-syntax": [
        "error",
        {
          selector: `JSXAttribute[name.name='className'] Literal[value=${PHYSICAL_CLASS}]`,
          message: PHYSICAL_CLASS_MESSAGE,
        },
        {
          selector: `JSXAttribute[name.name='className'] TemplateElement[value.raw=${PHYSICAL_CLASS}]`,
          message: PHYSICAL_CLASS_MESSAGE,
        },
        {
          selector: `CallExpression[callee.name=/^(cn|cva)$/] Literal[value=${PHYSICAL_CLASS}]`,
          message: PHYSICAL_CLASS_MESSAGE,
        },
      ],
    },
  },
  // Override default ignores of eslint-config-next.
  globalIgnores([
    // Default ignores of eslint-config-next:
    ".next/**",
    "out/**",
    "build/**",
    "next-env.d.ts",
    // Test and tooling output:
    "coverage/**",
    "playwright-report/**",
    "test-results/**",
    "blob-report/**",
  ]),
]);

export default eslintConfig;
