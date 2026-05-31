<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth.php';

class LikeController {

    // GET /api/likes — список избранного текущего пользователя
    public static function index(): void {
        $currentUser = requireAuth();
        $userId = $currentUser['id'];
        $db = getDB();
        $stmt = $db->prepare('
            SELECT
                l.id,
                l.book_id,
                b.title,
                b.author,
                b.cover_url,
                b.price,
                g.name AS genre_name,
                g.slug AS genre_slug
            FROM likes l
            JOIN books b ON l.book_id = b.id
            LEFT JOIN genres g ON b.genre_id = g.id
            WHERE l.user_id = ?
            ORDER BY l.id DESC
        ');
        $stmt->execute([$userId]);
        $likes = $stmt->fetchAll();
        foreach ($likes as &$l) {
            $l['id']      = (int)$l['id'];
            $l['book_id'] = (int)$l['book_id'];
            $l['price']   = (float)$l['price'];
        }
        http_response_code(200);
        echo json_encode($likes);
    }

    // POST /api/likes/{bookId} — добавить/убрать из избранного
    public static function toggle(int $bookId): void {
        $currentUser = requireAuth();
        $userId = $currentUser['id'];
        $db = getDB();

        $bookCheck = $db->prepare('SELECT id FROM books WHERE id = ?');
        $bookCheck->execute([$bookId]);
        if (!$bookCheck->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Книга не найдена']);
            return;
        }

        $existing = $db->prepare('SELECT id FROM likes WHERE user_id = ? AND book_id = ?');
        $existing->execute([$userId, $bookId]);
        $like = $existing->fetch();

        if ($like) {
            $db->prepare('DELETE FROM likes WHERE user_id = ? AND book_id = ?')
               ->execute([$userId, $bookId]);
            http_response_code(200);
            echo json_encode(['message' => 'Убрано из избранного', 'liked' => false]);
        } else {
            $db->prepare('INSERT INTO likes (user_id, book_id) VALUES (?, ?)')
               ->execute([$userId, $bookId]);
            http_response_code(201);
            echo json_encode(['message' => 'Добавлено в избранное', 'liked' => true]);
        }
    }
}