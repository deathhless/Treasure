// Сохраняем пользователя в localStorage после логина/регистрации
function saveUser(user) {
  localStorage.setItem('user', JSON.stringify(user));
  // JSON.stringify — превращаем объект в строку для хранения
}
// Читаем пользователя из localStorage
function getUser() {
  const raw = localStorage.getItem('user');
  if (!raw || raw === 'undefined' || raw === 'null') return null;
  try {
    return JSON.parse(raw);
  } catch (e) {
    localStorage.removeItem('user');
    return null;
  }
}
// Удаляем пользователя (выход из системы)
function removeUser() {
  localStorage.removeItem('user');
}
// Проверяем залогинен ли пользователь
function isLoggedIn() {
  return getUser() !== null;
}
// Проверяем является ли пользователь администратором
function isAdmin() {
  const user = getUser();
  return user && user.role === 'admin';
  // && — если user null, не пытаемся читать user.role
}
// Защита страниц — редирект если не залогинен
function requireLogin() {
  if (!isLoggedIn()) {
    window.location.href = '/treasure/public/login.html';
    // перенаправляем на страницу входа
  }
}
// Защита страниц — редирект если не администратор
function requireAdmin() {
  const user = getUser();
  if (!user || user.role !== 'admin') {
    window.location.href = '/treasure/public/index.html';
  }
}
// Выход из системы
async function logout() {
  try {
    await api.post('/logout'); // уничтожаем сессию на сервере
  } catch (e) {}              // тихо игнорируем ошибку сети
  removeUser();               // чистим localStorage
  window.location.href = '/treasure/public/login.html';
}