<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../utils/Logger.php';

class BookController {

    public static function index(): void {
        $db   = getDB();
        $stmt = $db->query('
            SELECT
                b.id,
                b.title,
                b.author,
                b.description,
                b.price,
                b.cover_url,
                b.views_count,
                g.name AS genre_name,
                g.slug AS genre_slug
            FROM books b
            LEFT JOIN genres g ON b.genre_id = g.id
            ORDER BY b.id DESC
        ');
        $books = $stmt->fetchAll();
        foreach ($books as &$book) {
            $book['id']          = (int)$book['id'];
            $book['price']       = (float)$book['price'];
            $book['views_count'] = (int)$book['views_count'];
        }
        http_response_code(200);
        echo json_encode($books);
    }

    public static function show(int $id): void {
        $db   = getDB();
        $stmt = $db->prepare('
            SELECT
                b.*,
                g.name AS genre_name,
                g.slug AS genre_slug,
                ROUND(AVG(CAST(r.rating AS FLOAT)), 1) AS avg_rating,
                COUNT(r.id) AS reviews_count
            FROM books b
            LEFT JOIN genres  g ON b.genre_id = g.id
            LEFT JOIN reviews r ON b.id = r.book_id
            WHERE b.id = ?
            GROUP BY
                b.id, b.title, b.author, b.description,
                b.price, b.cover_url, b.content,
                b.genre_id, b.views_count,
                g.name, g.slug
        ');
        $stmt->execute([$id]);
        $book = $stmt->fetch();
        if (!$book) {
            http_response_code(404);
            echo json_encode(['error' => 'Книга не найдена']);
            return;
        }
        $db->prepare('UPDATE books SET views_count = views_count + 1 WHERE id = ?')
           ->execute([$id]);
        $book['id']            = (int)$book['id'];
        $book['price']         = (float)$book['price'];
        $book['views_count']   = (int)$book['views_count'];
        $book['avg_rating']    = $book['avg_rating'] ? (float)$book['avg_rating'] : null;
        $book['reviews_count'] = (int)$book['reviews_count'];
        http_response_code(200);
        echo json_encode($book);
    }

    public static function store(array $body): void {
        $currentUser = requireAdmin();
        $title       = trim($body['title']       ?? '');
        $author      = trim($body['author']      ?? '');
        $description = trim($body['description'] ?? '');
        $price       = $body['price']    ?? 0;
        $cover_url   = trim($body['cover_url']   ?? '');
        $content     = trim($body['content']     ?? '');
        $genre_id    = $body['genre_id'] ?? null;

        if (!$title || !$author) {
            http_response_code(422);
            echo json_encode(['error' => 'Название и автор обязательны']);
            return;
        }
        if (!is_numeric($price) || $price < 0) {
            http_response_code(422);
            echo json_encode(['error' => 'Некорректная цена']);
            return;
        }
        $db   = getDB();
        $stmt = $db->prepare('
            INSERT INTO books (title, author, description, price, cover_url, content, genre_id, views_count)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0)
        ');
        $stmt->execute([$title, $author, $description, $price, $cover_url, $content, $genre_id]);
        $newId = (int)$db->lastInsertId();

        Logger::write('INFO', 'book.created', (int)$currentUser['id'], ['title' => $title]);

        http_response_code(201);
        echo json_encode(['message' => 'Книга добавлена', 'id' => $newId]);
    }

    public static function update(int $id, array $body): void {
        $currentUser = requireAdmin();
        $db          = getDB();
        $check       = $db->prepare('SELECT id FROM books WHERE id = ?');
        $check->execute([$id]);
        if (!$check->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Книга не найдена']);
            return;
        }
        $title       = trim($body['title']       ?? '');
        $author      = trim($body['author']      ?? '');
        $description = trim($body['description'] ?? '');
        $price       = $body['price']    ?? 0;
        $cover_url   = trim($body['cover_url']   ?? '');
        $content     = trim($body['content']     ?? '');
        $genre_id    = $body['genre_id'] ?? null;

        if (!$title || !$author) {
            http_response_code(422);
            echo json_encode(['error' => 'Название и автор обязательны']);
            return;
        }
        $stmt = $db->prepare('
            UPDATE books
            SET title = ?, author = ?, description = ?,
                price = ?, cover_url = ?, content = ?, genre_id = ?
            WHERE id = ?
        ');
        $stmt->execute([$title, $author, $description, $price, $cover_url, $content, $genre_id, $id]);

        Logger::write('INFO', 'book.updated', (int)$currentUser['id'], ['book_id' => $id]);

        http_response_code(200);
        echo json_encode(['message' => 'Книга обновлена']);
    }

    public static function destroy(int $id): void {
        $currentUser = requireAdmin();
        $db          = getDB();
        $check       = $db->prepare('SELECT id FROM books WHERE id = ?');
        $check->execute([$id]);
        if (!$check->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Книга не найдена']);
            return;
        }
        $db->prepare('DELETE FROM books WHERE id = ?')->execute([$id]);

        Logger::write('INFO', 'book.deleted', (int)$currentUser['id'], ['book_id' => $id]);

        http_response_code(200);
        echo json_encode(['message' => 'Книга удалена']);
    }
}
