// Базовый URL нашего PHP API — все запросы идут сюда
const API_URL = 'http://localhost/treasure/api';
// Универсальная функция для запросов к API
// метод — GET/POST/PUT/DELETE
// path  — например '/books' или '/login'
// data  — тело запроса для POST/PUT (объект)
async function request(method, path, data = null) {
  const options = {
    method,// HTTP метод
    credentials: 'include', // передаём куки сессии с каждым запросом
    headers: { 'Content-Type': 'application/json' }, // говорим серверу что шлём JSON
  };
  if (data) {
    options.body = JSON.stringify(data); // сериализуем объект в JSON-строку
  }
  const res = await fetch(API_URL + path, options);
  // fetch возвращает Promise — ждём ответа
  const json = await res.json();
  // парсим тело ответа из JSON в объект
  if (!res.ok) {
    // res.ok = true если статус 200-299, иначе false
    throw new Error(json.error || 'Ошибка сервера');
    // бросаем ошибку с текстом от PHP API
  }
  return json; // возвращаем данные если всё ок
}
// Готовые shortcut-функции для удобства
const api = {
  get:(path) => request('GET', path),
  post:(path, data)=> request('POST', path, data),
  put:(path, data) => request('PUT', path, data),
  delete: (path) => request('DELETE', path),
};