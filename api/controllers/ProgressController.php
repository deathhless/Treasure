<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth.php';

class ProgressController {

    // GET /api/progress — весь прогресс текущего пользователя
    public static function index(): void {
        $currentUser = requireAuth();
        $userId = $currentUser['id'];
        $db = getDB();
        $stmt = $db->prepare('
            SELECT
                rp.book_id,
                rp.current_page,
                rp.progress_percent,
                rp.updated_at,
                b.title,
                b.author,
                b.cover_url,
                g.name AS genre_name
            FROM reading_progress rp
            JOIN books b ON rp.book_id = b.id
            LEFT JOIN genres g ON b.genre_id = g.id
            WHERE rp.user_id = ?
            ORDER BY rp.updated_at DESC
        ');
        $stmt->execute([$userId]);
        $progress = $stmt->fetchAll();
        foreach ($progress as &$p) {
            $p['book_id']      = (int)$p['book_id'];
            $p['current_page'] = (int)$p['current_page'];
            $p['progress_percent']      = (int)$p['progress_percent'];
        }
        http_response_code(200);
        echo json_encode($progress);
    }

    // PUT /api/progress/{bookId} — сохранить прогресс
    public static function update(int $bookId, array $body): void {
        $currentUser = requireAuth();
        $userId      = $currentUser['id'];
        $currentPage = (int)($body['current_page'] ?? 1);
        $progress_percent     = (int)($body['progress_percent'] ?? 0);

        if ($currentPage < 1) {
            http_response_code(422);
            echo json_encode(['error' => 'Страница не может быть меньше 1']);
            return;
        }
        if ($progress_percent < 0 || $progress_percent > 100) {
            http_response_code(422);
            echo json_encode(['error' => 'Процент от 0 до 100']);
            return;
        }

        $db = getDB();

        $purchased = $db->prepare('SELECT id FROM purchases WHERE user_id = ? AND book_id = ?');
        $purchased->execute([$userId, $bookId]);
        if (!$purchased->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Книга не куплена']);
            return;
        }

        $stmt = $db->prepare("
            MERGE reading_progress AS target
            USING (SELECT ? AS user_id, ? AS book_id) AS source
                ON target.user_id = source.user_id AND target.book_id = source.book_id
            WHEN MATCHED THEN
                UPDATE SET
                    current_page = ?,
                    progress_percent = ?,
                    updated_at = GETDATE()
            WHEN NOT MATCHED THEN
                INSERT (user_id, book_id, current_page, progress_percent, updated_at)
                VALUES (?, ?, ?, ?, GETDATE());
        ");
        $stmt->execute([
            $userId, $bookId,
            $currentPage, $progress_percent,
            $userId, $bookId, $currentPage, $progress_percent,
        ]);

        http_response_code(200);
        echo json_encode([
            'message'      => 'Прогресс сохранён',
            'current_page' => $currentPage,
            'progress_percent'      => $progress_percent,
        ]);
    }
}