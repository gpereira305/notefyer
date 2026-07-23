<?php


require_once __DIR__ . '/vendor/autoload.php';

use Notefyer\RabbitMQ;

$host = getenv('RABBITMQ_HOST') ?: 'rabbitmq';
$port = (int)(getenv('RABBITMQ_PORT') ?: '5672');
$user = getenv('RABBITMQ_USER') ?: 'guest';
$pass = getenv('RABBITMQ_PASSWORD') ?: 'guest';
$queue = getenv('RABBITMQ_QUEUE') ?: 'notifications';

$dbHost = getenv('DB_HOST');
$dbName = getenv('MYSQL_DATABASE');
$dbUser = getenv('MYSQL_USER');
$dbPass = getenv('MYSQL_PASSWORD');

$pdo = new PDO(
    "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
    $dbUser,
    $dbPass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$rabbitmq = new RabbitMQ($host, $port, $user, $pass, $queue);

$callback = function ($message) use ($pdo) {
    $data = json_decode($message->body, true);

    try {
        $stmt = $pdo->prepare("UPDATE notifications SET status = 'PROCESSED' WHERE id = ?");
        $stmt->execute([$data['id']]);

        echo "Processed notification ID: {$data['id']}\n";
    } catch (PDOException $e) {
        error_log("Failed to update notification: " . $e->getMessage());
    }
};

$rabbitmq->consume($callback);
$rabbitmq->close();