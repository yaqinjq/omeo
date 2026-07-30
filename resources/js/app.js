import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

let deferredInstallPrompt = null;

function setTheme(mode) {
  const root = document.documentElement;
  if (mode === 'dark') root.classList.add('dark');
  else root.classList.remove('dark');
  localStorage.setItem('theme', mode);
}

function initTheme() {
  const saved = localStorage.getItem('theme');
  if (saved === 'dark' || saved === 'light') return setTheme(saved);

  const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  setTheme(prefersDark ? 'dark' : 'light');
}

function initSidebar() {
  const btnMobile = document.getElementById('btnSidebar');
  const btnMobileClose = document.getElementById('btnSidebarClose');
  const btnDesktop = document.getElementById('btnSidebarDesktop');
  const sidebar = document.getElementById('sidebar');
  const layout = document.getElementById('appLayout');
  const overlay = document.getElementById('sidebarOverlay');

  if (!sidebar || !overlay) return;

  function openMobile() {
    sidebar.classList.remove('-translate-x-[120%]');
    sidebar.classList.add('translate-x-0');
    overlay.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    if (btnMobile) btnMobile.setAttribute('aria-expanded', 'true');
  }

  function closeMobile() {
    sidebar.classList.remove('translate-x-0');
    sidebar.classList.add('-translate-x-[120%]');
    overlay.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
    if (btnMobile) btnMobile.setAttribute('aria-expanded', 'false');
  }

  if (btnMobile) {
    btnMobile.addEventListener('click', () => {
      if (overlay.classList.contains('hidden')) openMobile();
      else closeMobile();
    });
  }

  if (btnMobileClose) btnMobileClose.addEventListener('click', closeMobile);
  overlay.addEventListener('click', closeMobile);

  sidebar.querySelectorAll('a.menu-item').forEach((item) => {
    item.addEventListener('click', () => {
      if (window.innerWidth < 768) closeMobile();
    });
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth >= 768) closeMobile();
  });

  if (layout && btnDesktop) {
    const states = ['full', 'mini', 'hidden'];
    let state = localStorage.getItem('sidebar_state') || 'full';
    if (!states.includes(state)) state = 'full';

    const applyDesktopState = (nextState) => {
      layout.classList.remove('sidebar-mini', 'sidebar-hidden');
      if (nextState === 'mini') layout.classList.add('sidebar-mini');
      if (nextState === 'hidden') layout.classList.add('sidebar-hidden');
      localStorage.setItem('sidebar_state', nextState);
    };

    applyDesktopState(state);

    btnDesktop.addEventListener('click', () => {
      const current = localStorage.getItem('sidebar_state') || 'full';
      const currentIndex = states.indexOf(current);
      const next = states[(currentIndex + 1) % states.length];
      applyDesktopState(next);
    });
  }
}

function initServiceWorker() {
  if (!('serviceWorker' in navigator)) return;

  const isLocalhost = ['localhost', '127.0.0.1'].includes(window.location.hostname);
  const isSecure = window.location.protocol === 'https:' || isLocalhost;
  if (!isSecure) return;

  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
      // Silent fallback if registration fails.
    });
  });
}

function initInstallPrompt() {
  const installBtn = document.getElementById('btnInstallApp');

  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredInstallPrompt = event;
    if (installBtn) installBtn.classList.remove('hidden');
  });

  window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null;
    if (installBtn) installBtn.classList.add('hidden');
  });

  if (!installBtn) return;

  installBtn.addEventListener('click', async () => {
    if (!deferredInstallPrompt) return;
    deferredInstallPrompt.prompt();
    try {
      await deferredInstallPrompt.userChoice;
    } catch (_) {
      // Ignore prompt errors.
    }
    deferredInstallPrompt = null;
    installBtn.classList.add('hidden');
  });
}

document.addEventListener('DOMContentLoaded', () => {
  initTheme();

  const toggle = document.getElementById('themeToggle');
  if (toggle) {
    toggle.addEventListener('click', () => {
      const isDark = document.documentElement.classList.contains('dark');
      setTheme(isDark ? 'light' : 'dark');
    });
  }

  initSidebar();
  initInstallPrompt();
  initServiceWorker();
});
