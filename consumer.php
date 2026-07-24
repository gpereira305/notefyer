<?php


require_once __DIR__ . '/vendor/autoload.php';

use Notefyer\RabbitMQ;

$host = getenv('RABBITMQ_HOST');
$port = (int)getenv('RABBITMQ_PORT');
$user = getenv('RABBITMQ_USER');
$pass = getenv('RABBITMQ_PASSWORD');
$queue = getenv('RABBITMQ_QUEUE');

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

    if (!is_array($data) || !isset($data['id'])) {
        error_log("Malformed message, dropping: {$message->body}");
        $message->nack(false); // requeue=false: don't loop forever on garbage
        return;
    }

    try {
        $stmt = $pdo->prepare("UPDATE notifications SET status = 'PROCESSED' WHERE id = ?");
        $stmt->execute([$data['id']]);

        echo "Processed notification ID: {$data['id']}\n";
        $message->ack();
    } catch (\Throwable $e) {
        error_log("Failed to update notification ID {$data['id']}: " . $e->getMessage());
        $message->nack(true); // requeue=true: try again on next delivery
    }
};

$rabbitmq->consume($callback);
$rabbitmq->close();