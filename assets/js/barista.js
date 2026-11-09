(() => {
  const $ = (selector, scope = document) => scope.querySelector(selector);
  const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];
  const root = $('.barista-layout');
  if (!root) return;

  let csrf = '';
  try { csrf = JSON.parse($('#barista-csrf')?.textContent || '{}').csrf_token || ''; } catch { csrf = ''; }
  const messagesEl = $('#barista-messages');
  const input = $('#barista-input');
  const sendButton = $('#barista-send');
  const feedback = $('#barista-feedback');
  const recSection = $('#barista-recommendations');
  const recGrid = $('#barista-product-grid');
  const conversation = [];
  let selectedShade = 'caramel';
  let busy = false;

  const escapeText = value => String(value ?? '');
  const currency = value => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', maximumFractionDigits: 0 }).format(Number(value) || 0);
  const preferences = () => ({
    taste: $('#barista-taste')?.value || 'surprise me',
    strength: $('#barista-strength')?.value || 'any',
    budget: Number($('#barista-budget')?.value || 0),
    shade: selectedShade,
    dessert_pairing: $('#barista-dessert-pair')?.checked === true
  });

  function scrollMessages() { messagesEl.scrollTop = messagesEl.scrollHeight; }
  function addMessage(role, text, quickReplies = []) {
    const article = document.createElement('article');
    article.className = `chat-message ${role === 'user' ? 'user-message' : 'assistant-message'}`;
    const avatar = document.createElement('div');
    avatar.className = 'chat-message-avatar';
    avatar.textContent = role === 'user' ? 'YOU' : 'K.';
    const bubble = document.createElement('div');
    bubble.className = 'chat-bubble';
    const sender = document.createElement('span');
    sender.className = 'chat-sender';
    sender.textContent = role === 'user' ? 'YOU' : 'K.O.D BARISTA';
    const time = document.createElement('time');
    time.textContent = 'JUST NOW';
    sender.append(time);
    const paragraph = document.createElement('p');
    paragraph.textContent = text;
    bubble.append(sender, paragraph);
    if (quickReplies.length) {
      const row = document.createElement('div');
      row.className = 'chat-suggestions';
      quickReplies.slice(0, 3).forEach(reply => {
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.prompt = reply;
        button.textContent = reply;
        row.append(button);
      });
      bubble.append(row);
    }
    article.append(avatar, bubble);
    messagesEl.append(article);
    scrollMessages();
    return { article, paragraph };
  }

  function showTyping() {
    const item = addMessage('assistant', 'Let me think of something lovely…');
    item.article.classList.add('typing-message');
    item.paragraph.classList.add('typing-dots');
    return item.article;
  }

  function renderProducts(products) {
    recGrid.replaceChildren();
    if (!Array.isArray(products) || !products.length) {
      recSection.hidden = true;
      return;
    }
    products.forEach((product, index) => {
      const card = document.createElement('article');
      card.className = 'barista-product-card';
      const imageWrap = document.createElement('div');
      imageWrap.className = 'barista-product-image';
      const image = document.createElement('img');
      image.alt = product.name ? `${product.name} at K.O.D` : 'K.O.D menu item';
      image.loading = 'lazy';
      try {
        const imageUrl = new URL(product.image_url, window.location.href);
        if (['http:', 'https:'].includes(imageUrl.protocol)) image.src = imageUrl.href;
      } catch { /* no image: keep the neutral image panel */ }
      const number = document.createElement('span');
      number.className = 'barista-product-number';
      number.textContent = `K.O.D PICK / 0${index + 1}`;
      imageWrap.append(image, number);
      const body = document.createElement('div');
      body.className = 'barista-product-copy';
      const category = document.createElement('span');
      category.className = 'barista-product-category';
      category.textContent = String(product.category || 'menu').toUpperCase();
      const name = document.createElement('h4');
      name.textContent = product.name || 'K.O.D favourite';
      const desc = document.createElement('p');
      desc.textContent = product.description || 'A lovely pick from the K.O.D menu.';
      const bottom = document.createElement('div');
      bottom.className = 'barista-product-bottom';
      const price = document.createElement('strong');
      price.textContent = currency(product.price);
      const add = document.createElement('button');
      add.type = 'button';
      add.className = 'button button-dark add-to-bag';
      add.dataset.productId = String(Number(product.id));
      add.dataset.productName = String(product.name || 'K.O.D menu item');
      add.dataset.productPrice = String(Number(product.price) || 0);
      add.dataset.productImage = String(product.image_url || '');
      add.innerHTML = 'Add to bag <span>↗</span>';
      bottom.append(price, add);
      body.append(category, name, desc, bottom);
      card.append(imageWrap, body);
      recGrid.append(card);
    });
    $('#barista-recommendation-count').textContent = `${products.length} K.O.D ${products.length === 1 ? 'PICK' : 'PICKS'} FOR YOU`;
    recSection.hidden = false;
    recSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  async function sendMessage(rawText, extraPreferences = null) {
    const content = String(rawText || '').trim();
    if (!content || busy) return;
    busy = true;
    sendButton.disabled = true;
    $('#barista-find-match').disabled = true;
    feedback.textContent = '';
    input.value = '';
    addMessage('user', content);
    conversation.push({ role: 'user', content });
    const typing = showTyping();
    try {
      const response = await fetch('api/barista-chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ csrf_token: csrf, messages: conversation.slice(-10), preferences: extraPreferences || preferences() })
      });
      const rawResponse = await response.text();
      let result;
      try {
        result = JSON.parse(rawResponse);
      } catch (parseError) {
        // Keep the raw server response in DevTools for diagnosis, but never show
        // a PHP traceback or server internals to café customers.
        console.error('K.O.D AI Barista returned non-JSON output:', {
          status: response.status,
          response: rawResponse.slice(0, 3000)
        });
        throw new Error(
          `The K.O.D server returned an invalid response (HTTP ${response.status}). ` +
          'Please check C:\\xampp\\apache\\logs\\error.log and share the PHP error line.'
        );
      }
      if (!result || typeof result !== 'object') {
        throw new Error('The K.O.D server returned an unexpected response. Please try again.');
      }
      if (!response.ok || !result.success) throw new Error(result.message || 'The barista could not reply just now.');
      typing.remove();
      addMessage('assistant', result.message || 'Let’s find something good from the menu.', result.quick_replies || []);
      conversation.push({ role: 'assistant', content: result.message || '' });
      renderProducts(result.products || []);
      feedback.textContent = result.mode === 'ai' ? 'PERSONALIZED WITH THE K.O.D AI BARISTA' : 'K.O.D MENU MATCH · LOCAL GUIDE MODE';
    } catch (error) {
      typing.remove();
      addMessage('assistant', error.message || 'I could not connect just now. Please try again.');
      feedback.textContent = 'PLEASE TRY AGAIN';
    } finally {
      busy = false;
      sendButton.disabled = false;
      $('#barista-find-match').disabled = false;
      input.focus();
    }
  }

  sendButton?.addEventListener('click', () => sendMessage(input.value));
  input?.addEventListener('keydown', event => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      sendMessage(input.value);
    }
  });
  input?.addEventListener('input', () => {
    input.style.height = 'auto';
    input.style.height = `${Math.min(input.scrollHeight, 120)}px`;
  });

  $('#barista-find-match')?.addEventListener('click', () => {
    const pref = preferences();
    const text = `Please recommend something for me using these preferences: taste ${pref.taste}; coffee strength ${pref.strength}; budget ${pref.budget > 0 ? currency(pref.budget) : 'no limit'}; colour preference ${pref.shade}; dessert pairing ${pref.dessert_pairing ? 'yes' : 'no'}.`;
    sendMessage(text, pref);
  });

  $('.barista-preferences')?.addEventListener('click', event => {
    const strengthButton = event.target.closest('[data-strength]');
    if (strengthButton) {
      $$('.barista-segment button').forEach(button => {
        const selected = button === strengthButton;
        button.classList.toggle('is-active', selected);
        button.setAttribute('aria-pressed', String(selected));
      });
      $('#barista-strength').value = strengthButton.dataset.strength;
    }
    const shadeButton = event.target.closest('[data-barista-shade]');
    if (shadeButton) {
      selectedShade = shadeButton.dataset.baristaShade;
      $$('.shade-dot').forEach(button => {
        const selected = button === shadeButton;
        button.classList.toggle('selected', selected);
        button.setAttribute('aria-pressed', String(selected));
      });
      $('#barista-shade-label').textContent = selectedShade.charAt(0).toUpperCase() + selectedShade.slice(1) + ' glow';
    }
  });

  messagesEl?.addEventListener('click', event => {
    const prompt = event.target.closest('[data-prompt]');
    if (prompt) sendMessage(prompt.dataset.prompt);
  });

  $('#barista-clear')?.addEventListener('click', () => {
    if (busy) return;
    conversation.length = 0;
    messagesEl.replaceChildren();
    recSection.hidden = true;
    addMessage('assistant', 'Fresh start. ☕ What are you in the mood for today? Tell me a flavour, a feeling or a budget and we’ll go from there.', ['Something smooth and creamy', 'A bold coffee', 'Coffee with a dessert']);
    feedback.textContent = '';
  });
})();
