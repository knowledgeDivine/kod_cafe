(() => {
  const CART_KEY = 'kodCafeCart';
  const money = value => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', maximumFractionDigits: 0 }).format(Number(value) || 0);
  const readCart = () => { try { const value = JSON.parse(localStorage.getItem(CART_KEY) || '[]'); return Array.isArray(value) ? value : []; } catch { return []; } };
  const writeCart = cart => { localStorage.setItem(CART_KEY, JSON.stringify(cart)); updateCartCount(); renderCartPage(); renderCheckout(); };
  const updateCartCount = () => { const count = readCart().reduce((sum, item) => sum + (Number(item.quantity) || 0), 0); document.querySelectorAll('[data-cart-count]').forEach(el => el.textContent = count); };
  const addToCart = data => {
    const cart = readCart(); const id = Number(data.id); const existing = cart.find(item => Number(item.id) === id);
    if (existing) existing.quantity = Math.min(20, Number(existing.quantity) + 1);
    else cart.push({ id, quantity: 1, name: data.name, price: Number(data.price), image: data.image || '' });
    writeCart(cart); showToast(`${data.name} added to your bag.`);
  };
  const showToast = message => {
    let toast = document.getElementById('kod-order-toast');
    if (!toast) { toast = document.createElement('div'); toast.id = 'kod-order-toast'; toast.className = 'order-toast'; toast.setAttribute('role','status'); document.body.appendChild(toast); }
    toast.textContent = message; toast.classList.add('visible'); clearTimeout(window.kodToastTimer); window.kodToastTimer = setTimeout(() => toast.classList.remove('visible'), 2200);
  };
  const cartLines = document.getElementById('cart-lines');
  const renderCartPage = () => {
    if (!cartLines) return;
    const cart = readCart();
    if (!cart.length) { cartLines.innerHTML = '<div class="cart-empty"><span class="eyebrow">A LITTLE ROOM FOR SOMETHING GOOD</span><h2>Your bag is<br><em>waiting to be filled.</em></h2><p>Explore the menu and add a coffee or something sweet.</p><a class="button button-dark" href="index.php#menu">Explore the menu <span>↗</span></a></div>'; document.getElementById('cart-item-count').textContent = '0'; document.getElementById('cart-subtotal').textContent = money(0); document.getElementById('cart-checkout')?.classList.add('is-disabled'); return; }
    document.getElementById('cart-checkout')?.classList.remove('is-disabled');
    cartLines.innerHTML = cart.map(item => `<article class="cart-line" data-cart-id="${Number(item.id)}"><img src="${escapeHtml(item.image || '')}" alt=""><div class="cart-line-copy"><h3>${escapeHtml(item.name)}</h3><span>${money(item.price)}</span><div class="quantity-control"><button type="button" data-cart-action="minus" aria-label="Decrease quantity">−</button><strong>${Number(item.quantity)}</strong><button type="button" data-cart-action="plus" aria-label="Increase quantity">+</button></div></div><div class="cart-line-end"><strong>${money(Number(item.price) * Number(item.quantity))}</strong><button type="button" class="cart-remove" data-cart-action="remove">Remove</button></div></article>`).join('');
    const total = cart.reduce((sum,item) => sum + Number(item.price) * Number(item.quantity), 0);
    document.getElementById('cart-item-count').textContent = String(cart.reduce((sum,item) => sum + Number(item.quantity), 0)); document.getElementById('cart-subtotal').textContent = money(total);
  };
  const renderCheckout = () => {
    const target = document.getElementById('checkout-lines'); if (!target) return;
    const cart = readCart();
    if (!cart.length) { target.innerHTML = '<p>Your bag is empty. <a href="index.php#menu">Return to the menu.</a></p>'; document.querySelector('.checkout-submit')?.setAttribute('disabled','disabled'); document.getElementById('checkout-total').textContent = money(0); return; }
    document.querySelector('.checkout-submit')?.removeAttribute('disabled');
    target.innerHTML = cart.map(item => `<div class="checkout-line"><span>${Number(item.quantity)} × ${escapeHtml(item.name)}</span><strong>${money(Number(item.price) * Number(item.quantity))}</strong></div>`).join('');
    const subtotal = cart.reduce((sum,item) => sum + Number(item.price) * Number(item.quantity), 0);
    const rewardsBox = document.querySelector('.checkout-rewards[data-points-available]');
    const points = Number(rewardsBox?.dataset.pointsAvailable || 0);
    const useRewards = document.getElementById('redeem-points')?.checked === true;
    const redeemablePoints = useRewards ? Math.min(Math.floor(points / 100) * 100, Math.floor(subtotal / 500) * 100) : 0;
    const discount = Math.floor(redeemablePoints / 100) * 500;
    document.getElementById('checkout-subtotal')?.replaceChildren(document.createTextNode(money(subtotal)));
    const discountRow = document.getElementById('checkout-discount-row');
    if (discountRow) discountRow.hidden = discount <= 0;
    const discountValue = document.getElementById('checkout-discount');
    if (discountValue) discountValue.textContent = '−' + money(discount);
    document.getElementById('checkout-total').textContent = money(Math.max(0, subtotal - discount));
  };
  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));

  document.addEventListener('click', event => {
    const add = event.target.closest('.add-to-bag');
    if (add) { addToCart({ id: add.dataset.productId, name: add.dataset.productName, price: add.dataset.productPrice, image: add.dataset.productImage }); return; }
    const action = event.target.closest('[data-cart-action]');
    if (!action) return;
    const row = action.closest('[data-cart-id]'); const id = Number(row?.dataset.cartId); const cart = readCart(); const item = cart.find(product => Number(product.id) === id); if (!item) return;
    if (action.dataset.cartAction === 'plus') item.quantity = Math.min(20, Number(item.quantity) + 1);
    if (action.dataset.cartAction === 'minus') item.quantity = Number(item.quantity) - 1;
    if (action.dataset.cartAction === 'remove' || item.quantity <= 0) writeCart(cart.filter(product => Number(product.id) !== id)); else writeCart(cart);
  });

  document.querySelectorAll('input[name="fulfillment"]').forEach(input => input.addEventListener('change', () => {
    const delivery = document.querySelector('input[name="fulfillment"]:checked')?.value === 'delivery'; const wrap = document.getElementById('delivery-address-wrap'); if (wrap) { wrap.hidden = !delivery; const field = wrap.querySelector('textarea'); if (field) field.required = delivery; }
  }));
  document.getElementById('redeem-points')?.addEventListener('change', renderCheckout);
  const checkoutForm = document.getElementById('checkout-form');
  checkoutForm?.addEventListener('submit', async event => {
    event.preventDefault(); const feedback = document.getElementById('checkout-feedback'); const button = checkoutForm.querySelector('button[type="submit"]');
    const cart = readCart(); if (!cart.length) { feedback.textContent = 'Your bag is empty. Add something from the menu first.'; return; }
    const formData = new FormData(checkoutForm); const delivery = formData.get('fulfillment') === 'delivery';
    if (delivery && !String(formData.get('delivery_address') || '').trim()) { feedback.textContent = 'Please enter your delivery address.'; return; }
    const payload = Object.fromEntries(formData.entries()); payload.items = cart.map(item => ({ id: Number(item.id), quantity: Number(item.quantity) }));
    button.disabled = true; button.classList.add('is-loading'); feedback.textContent = 'Sending your order to K.O.D…';
    try {
      const csrf = document.querySelector('meta[name="csrf-token"]')?.content || payload.csrf_token;
      const response = await fetch('api/create-order.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ ...payload, csrf_token: csrf }) });
      const result = await response.json(); if (!response.ok || !result.success) throw new Error(result.message || 'Could not place your order.');
      localStorage.removeItem(CART_KEY); updateCartCount(); window.location.href = result.redirect;
    } catch (error) { feedback.textContent = error.message || 'Please try again.'; button.disabled = false; button.classList.remove('is-loading'); }
  });
  updateCartCount(); renderCartPage(); renderCheckout();
})();
