const STORAGE_KEY = "theme";
const root = document.documentElement;
const osDark = matchMedia("(prefers-color-scheme: dark)");

function osTheme() {
  return osDark.matches ? "dark" : "light";
}

function currentTheme() {
  return root.dataset.theme ?? osTheme();
}

function store(theme) {
  try {
    if (theme) {
      localStorage.setItem(STORAGE_KEY, theme);
    } else {
      localStorage.removeItem(STORAGE_KEY);
    }
  } catch {
    // Storage blocked: the choice lasts until the next reload.
  }
}

export function initThemeToggle(button) {
  function updateButton() {
    button.setAttribute("aria-pressed", String(currentTheme() === "dark"));
  }

  button.addEventListener("click", () => {
    const next = currentTheme() === "dark" ? "light" : "dark";

    if (next === osTheme()) {
      delete root.dataset.theme;
      store(null);
    } else {
      root.dataset.theme = next;
      store(next);
    }

    updateButton();
  });

  osDark.addEventListener("change", updateButton);

  updateButton();
  button.hidden = false;
}
