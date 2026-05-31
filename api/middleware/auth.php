<?php
function requireAuth(): array {//проверка, что пользователь вошел в систему
    if(session_status()===PHP_SESSION_NONE) session_start();//запущена ли сессия?
    if(empty($_SESSION['user'])){//если пользователь не найден
        http_response_code(401);//ошибка авторизации
        echo json_encode(['error'=>'Unauthorized']);//сообщение об ошибке
        exit;
    }
    return $_SESSION['user'];//возврат текущего пользователя
}
function requireAdmin(): array {
    $user=requireAuth();//проверка пользователь вошел
    if($user['role'] !=='admin'){//проверка роли
        http_response_code(403);//пользователь вошел, но доступа нет
        echo json_encode(['error'=>'Forbidden: admin only']);
        exit;
    }
    return $user;//возвращаем данные админа
}
function requireUser(): ?array {//возврат пользователя либо нулл
    if(session_status()===PHP_SESSION_NONE) session_start();//проверяем и запускаем
    return $_SESSION['user'] ?? null;//если есть возвращаем значение, если нет-нулл
}