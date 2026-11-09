(() => {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const toast = (message, type = 'success') => {
    let node = document.getElementById('kod-personalization-toast');
    if (!node) {
      node = document.createElement('div');
      node.id = 'kod-personalization-toast';
      node.className = 'kod-personalization-toast';
      node.setAttribute('role', 'status');
      node.setAttribute('aria-live', 'polite');
      document.body.appendChild(node);
    }
    node.textContent = message;
    node.dataset.type = type;
    node.classList.add('is-visible');
    window.clearTimeout(node._hideTimer);
    node._hideTimer = window.setTimeout(() => node.classList.remove('is-visible'), 3200);
  };

  document.addEventListener('click', async event => {
    const button = event.target.closest('[data-favorite-id]');
    if (!button) return;
    event.preventDefault();
    event.stopPropagation();
    const id = Number(button.dataset.favoriteId);
    if (!Number.isInteger(id) || id < 1) return;
    button.disabled = true;
    button.classList.add('is-saving');
    try {
      const response = await fetch('api/favorites.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ menu_item_id: id, csrf_token: csrf })
      });
      const result = await response.json();
      if (response.status === 401) {
        toast('Sign in to keep your favourite K.O.D drinks.', 'error');
        window.setTimeout(() => { window.location.href = result.login_url || 'login.php'; }, 900);
        return;
      }
      if (!response.ok || !result.success) throw new Error(result.message || 'Could not update favourites.');
      document.querySelectorAll(`[data-favorite-id="${id}"]`).forEach(control => {
        control.classList.toggle('is-favorite', result.is_favorite);
        control.setAttribute('aria-pressed', result.is_favorite ? 'true' : 'false');
        if (control.id === 'showroom-favorite') control.textContent = result.is_favorite ? '♥ Saved to favourites' : '♡ Save to favourites';
        else control.textContent = result.is_favorite ? '♥' : '♡';
        control.setAttribute('aria-label', result.is_favorite ? 'Remove from favourites' : 'Save to favourites');
      });
      document.dispatchEvent(new CustomEvent('kod:favorite-updated', { detail: { menuItemId: id, isFavorite: result.is_favorite } }));
      toast(result.message);
      if (!result.is_favorite && button.closest('.favorite-product-card')) {
        const card = button.closest('.favorite-product-card');
        card.remove();
        const count = document.querySelectorAll('.favorite-product-card').length;
        const summary = document.querySelector('.favorites-summary span:first-child');
        if (summary) summary.textContent = `${count} SAVED ${count === 1 ? 'FAVOURITE' : 'FAVOURITES'}`;
        if (count === 0) window.location.reload();
      }
    } catch (error) {
      toast(error.message || 'Something went wrong. Please try again.', 'error');
    } finally {
      button.disabled = false;
      button.classList.remove('is-saving');
    }
  });
})();
