<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/Logger.php';

class AuthController {

    public static function register(array $body): void {
        $name  = trim($body['name']     ?? '');
        $email = trim($body['email']    ?? '');
        $pass  = trim($body['password'] ?? '');

        if (!$name || !$email || !$pass) {
            http_response_code(400);
            echo json_encode(['error' => 'Заполните все поля']);
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Неверный формат email']);
            return;
        }

        $db    = getDB();
        $check = $db->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Пользователь с таким email уже существует']);
            return;
        }

        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $stmt = $db->prepare(
            "INSERT INTO users (name, email, password_hash, role, bonus_points, level)
             VALUES (?, ?, ?, 'reader', 0, 'Новичок')"
        );
        $stmt->execute([$name, $email, $hash]);
        $newId = (int)$db->lastInsertId();

        $s = $db->prepare('SELECT id, name, email, role, bonus_points, level FROM users WHERE id = ?');
        $s->execute([$newId]);
        $user = $s->fetch();

        $_SESSION['user'] = [
            'id'           => (int)$user['id'],
            'name'         => $user['name'],
            'email'        => $user['email'],
            'role'         => $user['role'],
            'bonus_points' => (int)$user['bonus_points'],
            'level'        => $user['level'],
        ];

        Logger::write('INFO', 'user.register', (int)$newId, ['email' => $email]);

        http_response_code(201);
        echo json_encode(['message' => 'Регистрация успешна', 'user' => $user]);
    }

    public static function login(array $body): void {
        $email = trim($body['email']    ?? '');
        $pass  = trim($body['password'] ?? '');

        if (!$email || !$pass) {
            http_response_code(400);
            echo json_encode(['error' => 'Введите email и пароль']);
            return;
        }

        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($pass, $user['password_hash'])) {
            Logger::write('WARNING', 'user.login_failed', null, ['email' => $email]);
            http_response_code(401);
            echo json_encode(['error' => 'Неверный email или пароль']);
            return;
        }

        $_SESSION['user'] = [
            'id'           => (int)$user['id'],
            'name'         => $user['name'],
            'email'        => $user['email'],
            'role'         => $user['role'],
            'bonus_points' => (int)$user['bonus_points'],
            'level'        => $user['level'],
        ];

        Logger::write('INFO', 'user.login', (int)$user['id'], ['email' => $email]);

        http_response_code(200);
        echo json_encode(['message' => 'Авторизация успешна', 'user' => $_SESSION['user']]);
    }

    public static function logout(): void {
        $userId = $_SESSION['user']['id'] ?? null;
        Logger::write('INFO', 'user.logout', $userId ? (int)$userId : null, []);

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']);
        }
        session_destroy();
        http_response_code(200);
        echo json_encode(['message' => 'Выход выполнен']);
    }
}
