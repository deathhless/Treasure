<?php
require_once __DIR__ . '/../config/db.php';
class GenreController {
    // GET /api/genres — список всех жанров
    public static function index(): void {
        $db = getDB();
        // берём жанры и считаем количество книг в каждом
        $stmt = $db->query('
            SELECT
                g.id,
                g.name,
                g.slug,
                COUNT(b.id) AS books_count   -- сколько книг в этом жанре
            FROM genres g
            LEFT JOIN books b ON g.id = b.genre_id  -- LEFT JOIN чтобы жанры без книг тоже попали
            GROUP BY g.id, g.name, g.slug
            ORDER BY g.name ASC -- алфавитный порядок
        ');
        $genres = $stmt->fetchAll();
        foreach ($genres as &$genre) {
            $genre['id'] = (int)$genre['id'];
            $genre['books_count'] = (int)$genre['books_count'];
        }
        http_response_code(200);
        echo json_encode($genres);
    }
}
