<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth.php';

class ReviewController {
    // POST /api/reviews — оставить отзыв
    public static function store(array $body): void {
        $currentUser = requireAuth(); // только авторизованный
        $userId = $currentUser['id'];
        $bookId = (int)($body['book_id'] ?? 0);
        $text = trim($body['text'] ?? '');
        $rating = (int)($body['rating'] ?? 0);
        if (!$bookId) {
            http_response_code(422);
            echo json_encode(['error' => 'Не указана книга']);
            return;
        }
        // рейтинг должен быть от 1 до 5 — совпадает с CHECK в БД
        if ($rating < 1 || $rating > 5) {
            http_response_code(422);
            echo json_encode(['error' => 'Рейтинг от 1 до 5']);
            return;
        }
        if (!$text) {
            http_response_code(422);
            echo json_encode(['error' => 'Текст отзыва обязателен']);
            return;
        }
        $db = getDB();
        // проверяем что книга существует
        $bookCheck = $db->prepare('SELECT id FROM books WHERE id = ?');
        $bookCheck->execute([$bookId]);
        if (!$bookCheck->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Книга не найдена']);
            return;
        }
        // проверяем что книга куплена — нельзя оставить отзыв без покупки
        $purchased = $db->prepare('SELECT id FROM purchases WHERE user_id = ? AND book_id = ?');
        $purchased->execute([$userId, $bookId]);
        if (!$purchased->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Сначала купите книгу']);
            return;
        }
        // UNIQUE (user_id, book_id) в БД защищает от дублей
        // но мы проверяем заранее чтобы дать понятную ошибку
        $dupCheck = $db->prepare('SELECT id FROM reviews WHERE user_id = ? AND book_id = ?');
        $dupCheck->execute([$userId, $bookId]);
        if ($dupCheck->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Вы уже оставили отзыв на эту книгу']);
            return;
        }
        $stmt = $db->prepare('
            INSERT INTO reviews (user_id, book_id, text, rating)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$userId, $bookId, $text, $rating]);
        http_response_code(201);
        echo json_encode(['message' => 'Отзыв добавлен']);
    }
    // GET /api/reviews?book_id=1 — получить отзывы книги
public static function index(): void {
    $bookId = (int)($_GET['book_id'] ?? 0);
    if (!$bookId) {
        http_response_code(422);
        echo json_encode(['error' => 'Не указана книга']);
        return;
    }
    $db = getDB();
    $stmt = $db->prepare('
        SELECT
            r.id,
            r.text,
            r.rating,
            r.created_at,
            u.name AS user_name
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        WHERE r.book_id = ?
        ORDER BY r.created_at DESC
    ');
    $stmt->execute([$bookId]);
    $reviews = $stmt->fetchAll();
    foreach ($reviews as &$r) {
        $r['id']     = (int)$r['id'];
        $r['rating'] = (int)$r['rating'];
    }
    http_response_code(200);
    echo json_encode($reviews);
}
}
