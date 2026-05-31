<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth.php';
class UserController {
    // GET /api/users — список пользователей (только админ)
    public static function index(): void {
        requireAdmin(); // посторонним не показываем список юзеров
        $db   = getDB();
        // НЕ выбираем password_hash — его нельзя отдавать клиенту никогда
        $stmt = $db->query('
            SELECT id, name, email, role, bonus_points, level, created_at
            FROM users
            ORDER BY id DESC
        ');
        $users = $stmt->fetchAll();
        foreach ($users as &$user) {
            $user['id'] = (int)$user['id'];
            $user['bonus_points'] = (int)$user['bonus_points'];
        }
        http_response_code(200);
        echo json_encode($users);
    }
    // GET /api/users/{id} — один пользователь
    public static function show(int $id): void {
        $currentUser = requireAuth(); // должен быть залогине
        // обычный пользователь может смотреть только свой профиль
        // админ может смотреть любой
        if ($currentUser['role'] !== 'admin' && $currentUser['id'] !== $id) {
            http_response_code(403);
            echo json_encode(['error' => 'Нет доступа']);
            return;
        }
        $db = getDB();
        $stmt = $db->prepare('
            SELECT id, name, email, role, bonus_points, level, created_at
            FROM users WHERE id = ?
        ');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'Пользователь не найден']);
            return;
        }
        $user['id'] = (int)$user['id'];
        $user['bonus_points'] = (int)$user['bonus_points'];
        http_response_code(200);
        echo json_encode($user);
    }
    // POST /api/users — создать пользователя (только админ)
    public static function store(array $body): void {
        requireAdmin();
        // переиспользуем логику регистрации из AuthController
        AuthController::register($body);
    }
    // PUT /api/users/{id} — редактировать пользователя
    public static function update(int $id, array $body): void {
        $currentUser = requireAuth();
        // обычный пользователь редактирует только себя
        if ($currentUser['role'] !== 'admin' && $currentUser['id'] !== $id) {
            http_response_code(403);
            echo json_encode(['error' => 'Нет доступа']);
            return;
        }
        $db = getDB();
        $check = $db->prepare('SELECT id FROM users WHERE id = ?');
        $check->execute([$id]);
        if (!$check->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Пользователь не найден']);
            return;
        }
        $name  = trim($body['name']  ?? '');
        $email = trim($body['email'] ?? '');
        if (!$name || !$email) {
            http_response_code(422);
            echo json_encode(['error' => 'Имя и email обязательны']);
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(422);
            echo json_encode(['error' => 'Некорректный email']);
            return;
        }
        // проверяем что новый email не занят ДРУГИМ пользователем
        $emailCheck = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $emailCheck->execute([$email, $id]);
        if ($emailCheck->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Email уже занят']);
            return;
        }
        // если передан новый пароль — хэшируем и обновляем его тоже
        if (!empty($body['password'])) {
            if (strlen($body['password']) < 6) {
                http_response_code(422);
                echo json_encode(['error' => 'Пароль минимум 6 символов']);
                return;
            }
            $hash = password_hash($body['password'], PASSWORD_BCRYPT);
            $stmt = $db->prepare('UPDATE users SET name = ?, email = ?, password_hash = ? WHERE id = ?');
            $stmt->execute([$name, $email, $hash, $id]);
        } else {
            // пароль не меняем — просто обновляем имя и email
            $stmt = $db->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
            $stmt->execute([$name, $email, $id]);
        }
        // обновляем сессию если пользователь редактирует сам себя
        if ($currentUser['id'] === $id) {
            $_SESSION['user']['name']  = $name;
            $_SESSION['user']['email'] = $email;
        }
        http_response_code(200);
        echo json_encode(['message' => 'Профиль обновлён']);
    }
    // DELETE /api/users/{id} — удалить пользователя (только админ)
    public static function destroy(int $id): void {
        requireAdmin();
        $db = getDB();
        $check = $db->prepare('SELECT id FROM users WHERE id = ?');
        $check->execute([$id]);
        if (!$check->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Пользователь не найден']);
            return;
        }
        // нельзя удалить самого себя — защита от случайной блокировки системы
        $currentUser = currentUser();
        if ($currentUser['id'] === $id) {
            http_response_code(400);
            echo json_encode(['error' => 'Нельзя удалить самого себя']);
            return;
        }
        // CASCADE удалит все покупки, отзывы, лайки и прогресс этого юзера
        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        http_response_code(200);
        echo json_encode(['message' => 'Пользователь удалён']);
    }
}
