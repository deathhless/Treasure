<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../utils/Logger.php';

class PurchaseController {

    public static function index(): void {
        $currentUser = requireAuth();
        $userId      = $currentUser['id'];
        $db          = getDB();
        $stmt        = $db->prepare('
            SELECT
                p.id,
                p.book_id,
                p.price_paid,
                p.bonus_earned,
                p.purchased_at,
                b.title,
                b.author,
                b.cover_url,
                g.name AS genre_name
            FROM purchases p
            JOIN books b ON p.book_id = b.id
            LEFT JOIN genres g ON b.genre_id = g.id
            WHERE p.user_id = ?
            ORDER BY p.purchased_at DESC
        ');
        $stmt->execute([$userId]);
        $purchases = $stmt->fetchAll();
        foreach ($purchases as &$p) {
            $p['id']           = (int)$p['id'];
            $p['book_id']      = (int)$p['book_id'];
            $p['price_paid']   = (float)$p['price_paid'];
            $p['bonus_earned'] = (int)$p['bonus_earned'];
        }
        http_response_code(200);
        echo json_encode($purchases);
    }

    public static function store(array $body): void {
        $currentUser = requireAuth();
        $userId      = $currentUser['id'];
        $bookId      = (int)($body['book_id']   ?? 0);
        $useBonus    = (bool)($body['use_bonus'] ?? false);

        if (!$bookId) {
            http_response_code(422);
            echo json_encode(['error' => 'Не указана книга']);
            return;
        }
        $db = getDB();

        $bookStmt = $db->prepare('SELECT id, price FROM books WHERE id = ?');
        $bookStmt->execute([$bookId]);
        $book = $bookStmt->fetch();
        if (!$book) {
            http_response_code(404);
            echo json_encode(['error' => 'Книга не найдена']);
            return;
        }

        $dupStmt = $db->prepare('SELECT id FROM purchases WHERE user_id = ? AND book_id = ?');
        $dupStmt->execute([$userId, $bookId]);
        if ($dupStmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Книга уже куплена']);
            return;
        }

        $userStmt = $db->prepare('SELECT bonus_points, level FROM users WHERE id = ?');
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();

        $price       = (float)$book['price'];
        $bonusPoints = (int)$user['bonus_points'];
        $pricePaid   = $price;
        $bonusUsed   = 0;

        if ($useBonus && $bonusPoints > 0) {
            $maxBonusDiscount = $price * 0.30;
            $bonusValue       = $bonusPoints / 100;
            $discount         = min($bonusValue, $maxBonusDiscount);
            $pricePaid        = max(0, $price - $discount);
            $bonusUsed        = (int)($discount * 100);
        }

        $bonusPercent = self::getBonusPercent($user['level']);
        $bonusEarned  = (int)($pricePaid * $bonusPercent / 100);

        $stmt = $db->prepare('
            INSERT INTO purchases (user_id, book_id, price_paid, bonus_earned)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$userId, $bookId, $pricePaid, $bonusEarned]);

        $newBonus = $bonusPoints - $bonusUsed + $bonusEarned;

        $countStmt = $db->prepare('SELECT COUNT(*) AS cnt FROM purchases WHERE user_id = ?');
        $countStmt->execute([$userId]);
        $purchaseCount = (int)$countStmt->fetch()['cnt'];
        $newLevel = self::calcLevel($purchaseCount);

        $db->prepare('UPDATE users SET bonus_points = ?, level = ? WHERE id = ?')
           ->execute([$newBonus, $newLevel, $userId]);

        $_SESSION['user']['bonus_points'] = $newBonus;
        $_SESSION['user']['level']        = $newLevel;

        Logger::write('INFO', 'book.purchase', (int)$userId, [
            'book_id'    => $bookId,
            'price_paid' => $pricePaid,
            'bonus_used' => $bonusUsed,
        ]);

        http_response_code(201);
        echo json_encode([
            'message'      => 'Покупка успешна',
            'price_paid'   => $pricePaid,
            'bonus_earned' => $bonusEarned,
            'bonus_used'   => $bonusUsed,
            'new_balance'  => $newBonus,
            'level'        => $newLevel,
        ]);
    }

    private static function getBonusPercent(string $level): int {
        return match($level) {
            'Новичок'   => 5,
            'Читатель'  => 7,
            'Библиофил' => 10,
            'Эксперт'   => 12,
            default     => 5,
        };
    }

    private static function calcLevel(int $count): string {
        return match(true) {
            $count >= 25 => 'Эксперт',
            $count >= 10 => 'Библиофил',
            $count >= 3  => 'Читатель',
            default      => 'Новичок',
        };
    }
}
