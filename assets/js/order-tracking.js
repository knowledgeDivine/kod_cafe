(() => {
  const root = document.getElementById('order-tracker');
  if (!root) return;

  const token = root.dataset.ref || '';
  const endpoint = root.dataset.endpoint || 'api/order-status.php';
  const historyUrl = root.dataset.historyUrl || 'order-history.php';
  const menuUrl = root.dataset.menuUrl || './#menu';
  const statusLabels = {
    pending: 'Order received',
    confirmed: 'Order confirmed',
    preparing: 'Being prepared',
    ready: 'Ready for collection',
    out_for_delivery: 'Out for delivery',
    completed: 'Completed',
    cancelled: 'Cancelled'
  };

  const escapeText = (value) => String(value ?? '');
  const formatMoney = (value) => `₦${Number(value || 0).toLocaleString('en-NG', { maximumFractionDigits: 0 })}`;
  const formatDate = (value) => {
    if (!value) return '';
    const date = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? '' : date.toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' });
  };
  const escapeHtml = (value) => escapeText(value).replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));

  function timelineFor(order) {
    if (order.status === 'cancelled') return ['pending', 'cancelled'];
    if (order.fulfillment === 'delivery') return ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'completed'];
    return ['pending', 'confirmed', 'preparing', 'ready', 'completed'];
  }

  function render(data) {
    const { order, items = [], history = [] } = data;
    const stages = timelineFor(order);
    const currentIndex = stages.indexOf(order.status);
    const cancelled = order.status === 'cancelled';
    const timeline = stages.map((stage, index) => {
      const complete = !cancelled && currentIndex >= 0 && index < currentIndex;
      const active = stage === order.status;
      return `<li class="tracker-step ${complete ? 'is-complete' : ''} ${active ? 'is-active' : ''} ${cancelled && active ? 'is-cancelled' : ''}">
        <span class="tracker-step-mark">${complete ? '✓' : (index + 1)}</span>
        <span class="tracker-step-copy"><strong>${escapeHtml(statusLabels[stage] || stage)}</strong><small>${active ? 'Current status' : (complete ? 'Completed' : 'Up next')}</small></span>
      </li>`;
    }).join('');

    const lines = items.map((item) => `<div class="tracker-item"><span>${Number(item.quantity)} × ${escapeHtml(item.item_name)}</span><strong>${formatMoney(item.line_total)}</strong></div>`).join('');
    const updates = history.map((event) => `<li><span class="tracker-history-dot"></span><div><strong>${escapeHtml(statusLabels[event.to_status] || event.to_status)}</strong><small>${escapeHtml(event.note || 'Order updated')} · ${escapeHtml(formatDate(event.created_at))}</small></div></li>`).join('');
    const eta = order.estimated_ready_at ? `<div class="tracker-eta"><span>ESTIMATED ${order.fulfillment === 'delivery' ? 'DELIVERY' : 'READY'} TIME</span><strong>${escapeHtml(formatDate(order.estimated_ready_at))}</strong><small>This is an estimate and may change as your order is prepared.</small></div>` : `<div class="tracker-eta tracker-eta-pending"><span>PREPARATION TIME</span><strong>We'll update this when available.</strong><small>Check back here for the latest order progress.</small></div>`;

    root.innerHTML = `
      <div class="tracker-overview">
        <div><span class="eyebrow">ORDER REFERENCE</span><strong class="tracker-reference">${escapeHtml(order.order_number)}</strong><small>Placed ${escapeHtml(formatDate(order.created_at))}</small></div>
        <span class="status-pill ${escapeHtml(order.status)}">${escapeHtml(statusLabels[order.status] || order.status)}</span>
      </div>
      ${cancelled ? '<div class="tracker-cancelled-note">This order has been cancelled. Please contact K.O.D if you need assistance.</div>' : ''}
      <div class="tracker-grid">
        <section class="tracker-main-card"><div class="tracker-card-heading"><span class="eyebrow">THE JOURNEY</span><span class="tracker-live"><i></i> Updates automatically</span></div><ol class="tracker-timeline">${timeline}</ol>${eta}</section>
        <aside class="tracker-side-card"><span class="eyebrow">MADE FRESH FOR YOU</span><h2>Your order</h2><div class="tracker-items">${lines}</div><div class="tracker-item tracker-total"><span>Total</span><strong>${formatMoney(order.total)}</strong></div><div class="tracker-detail"><span>Fulfilment</span><strong>${order.fulfillment === 'delivery' ? 'Delivery' : 'Pickup at K.O.D'}</strong></div><div class="tracker-detail"><span>Payment</span><strong>${order.payment_status === 'paid' ? 'Paid' : (order.fulfillment === 'delivery' ? 'Pay on delivery' : 'Pay on pickup')}</strong></div></aside>
      </div>
      <section class="tracker-history"><div class="tracker-card-heading"><span class="eyebrow">ORDER UPDATES</span><small id="tracker-last-checked">Refreshing every 20 seconds</small></div>${updates ? `<ol>${updates}</ol>` : '<p>No status updates yet. Your order updates will appear here.</p>'}</section>
      <div class="tracker-footer"><a class="button button-dark" href="${escapeHtml(historyUrl)}">My recent orders <span>↗</span></a><a class="text-link" href="${escapeHtml(menuUrl)}">Back to the menu</a></div>`;
  }

  function showError(message) {
    root.innerHTML = `<div class="tracker-message tracker-error"><strong>We couldn't load your order.</strong><p>${escapeHtml(message)}</p><button class="button button-dark" id="tracker-retry" type="button">Try again <span>↻</span></button></div>`;
    root.querySelector('#tracker-retry')?.addEventListener('click', load);
  }

  async function load() {
    try {
      const response = await fetch(`${endpoint}?ref=${encodeURIComponent(token)}&t=${Date.now()}`, { headers: { Accept: 'application/json' }, cache: 'no-store' });
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error(data.message || 'Please try again.');
      render(data);
      const checked = root.querySelector('#tracker-last-checked');
      if (checked) checked.textContent = `Last updated ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })} · refreshes every 20 seconds`;
    } catch (error) {
      if (!root.querySelector('.tracker-grid')) showError(error.message || 'Connection problem.');
      else {
        const checked = root.querySelector('#tracker-last-checked');
        if (checked) checked.textContent = 'Could not refresh just now. Retrying shortly.';
      }
    }
  }

  load();
  window.setInterval(load, 20000);
})();
