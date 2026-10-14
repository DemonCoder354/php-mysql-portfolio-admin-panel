(() => {
  const root = document.documentElement;

  // ----- Theme toggle -----
  const themeBtn = document.getElementById('themeToggle');
  themeBtn?.addEventListener('click', () => {
    const isDark = root.dataset.theme
      ? root.dataset.theme === 'dark'
      : matchMedia('(prefers-color-scheme: dark)').matches;
    root.dataset.theme = isDark ? 'light' : 'dark';
    try { localStorage.setItem('theme', root.dataset.theme); } catch (e) {}
  });

  // ----- Mobile menu -----
  const burger = document.getElementById('burger');
  const links = document.getElementById('navLinks');
  burger?.addEventListener('click', () => links.classList.toggle('is-open'));
  links?.addEventListener('click', (e) => {
    if (e.target.tagName === 'A') links.classList.remove('is-open');
  });

  // ----- Reveal blocks on scroll -----
  const revealEls = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });
    revealEls.forEach((el) => io.observe(el));
  } else {
    revealEls.forEach((el) => el.classList.add('is-visible'));
  }

  // ----- Highlight the active nav link -----
  const sections = [...document.querySelectorAll('main section[id]')];
  const navLinks = [...document.querySelectorAll('.nav__links a')];
  const spy = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      navLinks.forEach((a) =>
        a.classList.toggle('is-active', a.getAttribute('href') === '#' + entry.target.id));
    });
  }, { rootMargin: '-45% 0px -50% 0px' });
  sections.forEach((s) => spy.observe(s));

  // ----- Filter projects by technology -----
  const filters = document.getElementById('filters');
  filters?.addEventListener('click', (e) => {
    const chip = e.target.closest('.chip');
    if (!chip) return;
    filters.querySelectorAll('.chip').forEach((c) => c.classList.toggle('is-active', c === chip));
    const tag = chip.dataset.filter;
    document.querySelectorAll('.project').forEach((card) => {
      const techs = card.dataset.tech.split('|');
      card.classList.toggle('is-hidden', tag !== '*' && !techs.includes(tag));
    });
  });
})();
