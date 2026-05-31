<?php
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_path', '/');
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/BookController.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/controllers/GenreController.php';
require_once __DIR__ . '/controllers/PurchaseController.php';
require_once __DIR__ . '/controllers/ReviewController.php';
require_once __DIR__ . '/controllers/LikeController.php';
require_once __DIR__ . '/controllers/ProgressController.php';
//разбор uri
$requestUri = $_SERVER['REQUEST_URI'];//получает полный uri запроса
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);//каталог в котором нахлдится выполняемый PHP-скрипт
$path = str_replace($scriptDir, '', parse_url($requestUri, PHP_URL_PATH));//убираем директорию и гет параметры из пути
$path = trim($path, '/');//убираем слэши по краям
$segments = explode('/', $path);//разбиваем на сегменты
$method = $_SERVER['REQUEST_METHOD'];//определение метода
$body = json_decode(file_get_contents('php://input'), true) ?? [];//декодим джсон в пхп и если нет то выводим пустой массив
$resource = $segments[0] ?? '';//название ресурса
$id = isset($segments[1]) ? (int)$segments[1] : null;//получаем айди ресурса либо нулл

match(true) {
    // Auth
    $resource === 'register' && $method === 'POST'
        => AuthController::register($body),
    $resource === 'login' && $method === 'POST'
        => AuthController::login($body),
    $resource === 'logout' && $method === 'POST'
        => AuthController::logout(),

    // Books
    $resource === 'books' && $method === 'GET' && !$id
        => BookController::index(),
    $resource === 'books' && $method === 'GET' && $id
        => BookController::show($id),
    $resource === 'books' && $method === 'POST'
        => BookController::store($body),
    $resource === 'books' && $method === 'PUT' && $id
        => BookController::update($id, $body),
    $resource === 'books' && $method === 'DELETE' && $id
        => BookController::destroy($id),

    // Users
    $resource === 'users' && $method === 'GET' && !$id
        => UserController::index(),
    $resource === 'users' && $method === 'GET' && $id
        => UserController::show($id),
    $resource === 'users' && $method === 'POST'
        => UserController::store($body),
    $resource === 'users' && $method === 'PUT' && $id
        => UserController::update($id, $body),
    $resource === 'users' && $method === 'DELETE' && $id
        => UserController::destroy($id),

    // Genres
    $resource === 'genres' && $method === 'GET'
        => GenreController::index(),

    // Purchases
    $resource === 'purchases' && $method === 'GET'
        => PurchaseController::index(),
    $resource === 'purchases' && $method === 'POST'
        => PurchaseController::store($body),

    // Likes
    $resource === 'likes' && $method === 'GET'
        => LikeController::index(),
    $resource === 'likes' && $id && $method === 'POST'
        => LikeController::toggle($id),

    // Progress
    $resource === 'progress' && $method === 'GET'
        => ProgressController::index(),
    $resource === 'progress' && $id && $method === 'PUT'
        => ProgressController::update($id, $body),

    // Reviews
   $resource === 'reviews' && $method === 'GET'
    => ReviewController::index(),
$resource === 'reviews' && $method === 'POST'
    => ReviewController::store($body),

    default => (function() {
        http_response_code(404);
        echo json_encode(['error' => 'Route not found']);
    })()
};