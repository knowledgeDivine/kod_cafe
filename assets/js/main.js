(() => {
  const $ = (selector, scope = document) => scope.querySelector(selector);
  const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];
  const csrf = $('meta[name="csrf-token"]')?.content || '';

  // Mobile navigation
  const navToggle = $('#nav-toggle');
  const nav = $('#main-nav');
  navToggle?.addEventListener('click', () => {
    const opened = navToggle.getAttribute('aria-expanded') === 'true';
    navToggle.setAttribute('aria-expanded', String(!opened));
    nav?.classList.toggle('is-open', !opened);
    document.body.classList.toggle('nav-open', !opened);
  });
  $$('#main-nav a').forEach(link => link.addEventListener('click', () => {
    nav?.classList.remove('is-open');
    navToggle?.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('nav-open');
  }));

  // Header treatment
  const header = $('#site-header');
  const updateHeader = () => header?.classList.toggle('is-scrolled', window.scrollY > 24);
  window.addEventListener('scroll', updateHeader, { passive: true });
  updateHeader();

  // Reveal as each section enters the viewport
  const revealTargets = $$('.intro-section, .feature-section, .menu-card, .dessert-section, .coffee-studio, .story-section, .contact-section, .experience-card');
  revealTargets.forEach(el => el.classList.add('will-reveal'));
  if ('IntersectionObserver' in window) {
    const revealObserver = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          revealObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });
    revealTargets.forEach(el => revealObserver.observe(el));
  } else revealTargets.forEach(el => el.classList.add('is-visible'));

  // Direction-aware coffee splash when the visitor scrolls
  const splash = $('#scroll-splash');
  let lastY = window.scrollY;
  let lastSplash = 0;
  let splashTimer;
  window.addEventListener('scroll', () => {
    const currentY = window.scrollY;
    const delta = currentY - lastY;
    const now = Date.now();
    if (Math.abs(delta) > 14 && Math.abs(delta) < 180 && now - lastSplash > 520 && splash && currentY > 100) {
      splash.classList.remove('splash-up', 'splash-down', 'splash-active');
      void splash.offsetWidth;
      splash.classList.add(delta > 0 ? 'splash-down' : 'splash-up', 'splash-active');
      lastSplash = now;
      clearTimeout(splashTimer);
      splashTimer = setTimeout(() => splash.classList.remove('splash-active'), 850);
    }
    lastY = currentY;
  }, { passive: true });

  // Coffee shade studio
  const shadeOptions = $$('.shade-option');
  const shadeLabel = $('#shade-label');
  const coffeePreview = $('#coffee-preview');
  const recName = $('#recommendation-name');
  const recDescription = $('#recommendation-description');
  const recStrength = $('#recommendation-strength');
  const recPrice = $('#recommendation-price');
  const studioFeedback = $('#studio-feedback');
  const shadeData = {
    caramel: { hex: '#b97843', name: 'Caramel Glow', drink: 'Caramel Cloud Latte', description: 'A smooth espresso with silky milk, warm caramel and a soft, sweet finish.', strength: 'MEDIUM / SMOOTH', price: '₦3,800' },
    mocha: { hex: '#573729', name: 'Deep Mocha', drink: 'Dark Velvet Mocha', description: 'Bold espresso wrapped in deep chocolate and steamed milk. Rich, balanced and comforting.', strength: 'MEDIUM / RICH', price: '₦4,200' },
    latte: { hex: '#d7b991', name: 'Oat Silk', drink: 'Oat Silk Latte', description: 'A gentle espresso softened with creamy oat milk. Light sweetness, beautifully mellow.', strength: 'LIGHT / CREAMY', price: '₦4,000' },
    espresso: { hex: '#2b1a13', name: 'Midnight Espresso', drink: 'Midnight Double Espresso', description: 'A focused double shot with a deep roast profile and a clean, lingering finish.', strength: 'STRONG / BOLD', price: '₦2,800' },
    rose: { hex: '#c98d86', name: 'Rose Cloud', drink: 'Rose Cloud Latte', description: 'A soft latte with a delicate floral note and a silky finish. Gentle, fragrant and playful.', strength: 'LIGHT / FLORAL', price: '₦4,300' },
    matcha: { hex: '#9b9b70', name: 'Soft Matcha', drink: 'Matcha Milk Cloud', description: 'Earthy matcha blended with smooth milk for a calm, creamy pick-me-up.', strength: 'GENTLE / EARTHY', price: '₦4,000' }
  };
  let selectedShade = 'caramel';
  function chooseShade(shade) {
    const info = shadeData[shade];
    if (!info) return;
    selectedShade = shade;
    shadeOptions.forEach(button => {
      const selected = button.dataset.shade === shade;
      button.classList.toggle('selected', selected);
      button.setAttribute('aria-pressed', String(selected));
    });
    if (shadeLabel) shadeLabel.textContent = info.name.toUpperCase();
    if (coffeePreview) coffeePreview.style.setProperty('--coffee-shade', info.hex);
    if (recName) recName.textContent = info.drink;
    if (recDescription) recDescription.textContent = info.description;
    if (recStrength) recStrength.textContent = info.strength;
    if (recPrice) recPrice.textContent = info.price;
    if (studioFeedback) studioFeedback.textContent = '';
  }
  shadeOptions.forEach(button => button.addEventListener('click', () => chooseShade(button.dataset.shade)));

  $('#save-recommendation')?.addEventListener('click', async event => {
    const button = event.currentTarget;
    button.disabled = true;
    button.classList.add('is-loading');
    if (studioFeedback) studioFeedback.textContent = 'Finding your perfect pour…';
    try {
      const response = await fetch('api/recommend.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ shade: selectedShade, csrf_token: csrf })
      });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || 'Could not save your match.');
      if (studioFeedback) studioFeedback.textContent = `Your match is saved: ${result.recommendation.name}. Ask for it at K.O.D.`;
    } catch (error) {
      if (studioFeedback) studioFeedback.textContent = error.message || 'Something went wrong. Please try again.';
    } finally {
      button.disabled = false;
      button.classList.remove('is-loading');
    }
  });

  // AJAX contact form
  $('#contact-form')?.addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    const feedback = $('#contact-feedback');
    const button = $('button[type="submit"]', form);
    button.disabled = true;
    button.classList.add('is-loading');
    if (feedback) feedback.textContent = 'Sending your note…';
    try {
      const response = await fetch('api/contact.php', { method: 'POST', body: new FormData(form), headers: { 'Accept': 'application/json' } });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || 'Unable to send your note.');
      form.reset();
      if (feedback) feedback.textContent = result.message;
    } catch (error) {
      if (feedback) feedback.textContent = error.message || 'Please try again later.';
    } finally {
      button.disabled = false;
      button.classList.remove('is-loading');
    }
  });

  // Newsletter signup
  $('#newsletter-form')?.addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    const feedback = $('#newsletter-feedback');
    const button = $('button[type="submit"]', form);
    button.disabled = true;
    const email = new FormData(form).get('email');
    try {
      const response = await fetch('api/newsletter.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ email, csrf_token: csrf })
      });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || 'Could not subscribe.');
      form.reset();
      if (feedback) feedback.textContent = result.message;
    } catch (error) {
      if (feedback) feedback.textContent = error.message || 'Please try again.';
    } finally { button.disabled = false; }
  });

  // 3D dessert selector informs the Three.js scene
  $$('.dessert-control').forEach(button => button.addEventListener('click', () => {
    $$('.dessert-control').forEach(control => control.classList.toggle('active', control === button));
    window.dispatchEvent(new CustomEvent('kod:dessert', { detail: { dessert: button.dataset.dessert } }));
  }));
})();
