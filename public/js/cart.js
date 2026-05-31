// Корзина хранится в localStorage как массив объектов
// { id, title, author, price, cover_url }
// Читаем корзину из localStorage
function getCart() {
  const raw = localStorage.getItem('cart');
  return raw ? JSON.parse(raw) : []; // если корзины нет — пустой массив
}
// Сохраняем корзину в localStorage
function saveCart(cart) {
  localStorage.setItem('cart', JSON.stringify(cart));
}
// Добавляем книгу в корзину
function addToCart(book) {
  const cart = getCart();
  const exists = cart.some(item => item.id === book.id);
  // some — возвращает true если хоть один элемент подходит
  if (exists) {
    alert('Книга уже в корзине');
    return;
  }
  cart.push(book);  // добавляем книгу в конец массива
  saveCart(cart);
  updateCartBadge(); // обновляем счётчик в шапке
  alert(`"${book.title}" добавлена в корзину`);
}
// Удаляем книгу из корзины по id
function removeFromCart(bookId) {
  const cart = getCart().filter(item => item.id !== bookId);
  // filter — оставляем только те элементы у которых id не совпадает
  saveCart(cart);
}
// Очищаем корзину полностью (после покупки)
function clearCart() {
  localStorage.removeItem('cart');
  updateCartBadge();
}
// Обновляем счётчик товаров в шапке
function updateCartBadge() {
  const badge = document.getElementById('cart-badge');
  if (!badge) return; // шапка ещё не отрендерена — выходим
  const count = getCart().length;
  badge.textContent = count;
  badge.style.display = count > 0 ? 'inline' : 'none';
  // скрываем badge если корзина пуста
}