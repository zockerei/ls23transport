import { initThemeToggle } from "./theme.js";
import { initForm } from "./forms.js";

const themeToggle = document.querySelector(".theme-toggle");

if (themeToggle) {
  initThemeToggle(themeToggle);
}

for (const form of document.querySelectorAll(".form")) {
  initForm(form);
}
