const js = require("@eslint/js");
const htmlPlugin = require("eslint-plugin-html");
const globals = require("globals");

module.exports = [
  {
    ignores: [
      "script/yui/**",
      "script/flatpickr/**",
      "live/**",
      "lib/**",
      "vendor/**",
      "dist/**",
      "reports/**",
      "node_modules/**",
    ],
  },
  js.configs.recommended,
  {
    files: ["script/**/*.js", "script/**/*.inc"],
    plugins: {
      html: htmlPlugin,
    },
    settings: {
      "html/html-extensions": [".inc"],
    },
    languageOptions: {
      ecmaVersion: 5,
      sourceType: "script",
      globals: {
        ...globals.browser,
        YAHOO: "readonly",
        flatpickr: "readonly",
      },
    },
    rules: {
      indent: ["error", 2, { SwitchCase: 1 }],
      "no-trailing-spaces": "error",
      "no-unused-vars": ["warn", { args: "none" }],
      "no-redeclare": "warn",
    },
  },
];
