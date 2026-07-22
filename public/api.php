<?php
header('Content-Type: application/json; charset=utf-8');

$host = getenv('DB_HOST');
$db   = getenv('MYSQL_DATABASE');
$user = getenv('MYSQL_USER');
$pass = getenv('MYSQL_PASSWORD');

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Falha ao conectar ao banco de dados!']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $email = $data['email'] ?? null;
        $message = $data['message'] ?? null;

        if (!$email || !$message) {
            http_response_code(400);
            echo json_encode(['error' => 'Email e messagem são obrigatórios!']);
            exit;
        }

        $insertNotifications = $pdo->prepare("INSERT INTO notifications (email, message) VALUES (?, ?)");
        $insertNotifications->execute([$email, $message]);

        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        exit;
    } catch (PDOException $e) {
        error_log($e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Falha ao salvar notificação!']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $queryNotifications = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC");
        $notifications = $queryNotifications->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($notifications);
        exit;
    } catch (PDOException $e) {
        error_log($e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Falha ao carregar notificações!']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    try {
        $deleted = $pdo->exec("DELETE FROM notifications");
        echo json_encode(['success' => true, 'deleted' => $deleted]);
        exit;
    } catch (PDOException $e) {
        error_log($e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Falha ao limpar notificações!']);
        exit;
    }
}