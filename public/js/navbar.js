function renderNavbar() {
  const user = getUser();
  const html = `
    <nav class="navbar">
      <a href="/treasure/public/index.html">Treasure</a>
      <div class="navbar__links">
        <a href="/treasure/public/catalog.html">Каталог</a>
        ${user ? `
          <a href="/treasure/public/library.html">Библиотека</a>
          <a href="/treasure/public/cart.html">Корзина <span id="cart-badge" style="background:var(--gold); color:#fff; border-radius:50%; padding:1px 6px; font-size:12px; display:none;">0</span></a>
          <a href="/treasure/public/favorites.html">Избранное</a>
          <a href="/treasure/public/profile.html">${user.name}</a>
          <span class="navbar__bonus">${user.bonus_points}бонусов</span>
          ${user.role === 'admin' ? `
            <a href="/treasure/public/admin/books.html" class="navbar__admin">Админка</a>
          ` : ''}
          <button onclick="logout()" class="navbar__btn">Выйти</button>
         `:`
          <a href="/treasure/public/login.html">Войти</a>
          <a href="/treasure/public/register.html">Регистрация</a>
        `}
      </div>
    </nav>
  `;
  document.body.insertAdjacentHTML('afterbegin', html);
}

document.addEventListener('DOMContentLoaded', renderNavbar);
document.addEventListener('DOMContentLoaded', () => {
  if (typeof updateCartBadge === 'function') updateCartBadge();
});