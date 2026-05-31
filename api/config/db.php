<?php
function getDB(): PDO{//соединение с бд, возврат объектов типа пдо
    static $pdo=null;
    if($pdo !== null) return $pdo;
    $host='localhost';
    $dbname='treasurebd';
    $user='t';
    $pass='arlav270206';
    try{//блок обраблтки ошибок
        $pdo=new PDO(
            "sqlsrv:Server=$host;Database=$dbname;TrustServerCertificate=1",
            $user,
            $pass,
            [//начало массива настроек пдо
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,//любая ошибка бд будет выбрасывать исключение,удобно для откладки
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,//все резы как ассоциотивные массивы, то есть с названиями id=>1, а не 0=>1
            ]
        );
    }catch (PDOException $e){
        http_response_code(500);
        echo json_encode(['error'=>'DB connection failes: ' . $e->getMessage()]);
        exit;
    }
    return $pdo;
}