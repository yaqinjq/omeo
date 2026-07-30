const key = "theme";

export function initTheme() {
  const saved = localStorage.getItem(key);
  const prefersDark = window.matchMedia?.("(prefers-color-scheme: dark)").matches;
  const isDark = saved ? saved === "dark" : prefersDark;

  document.documentElement.classList.toggle("dark", isDark);
}

export function toggleTheme() {
  const isDark = document.documentElement.classList.toggle("dark");
  localStorage.setItem(key, isDark ? "dark" : "light");
}
