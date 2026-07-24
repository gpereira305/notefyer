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

$consumerCallback = static function ($message) use ($pdo) {
    $data = json_decode($message->body, true);

    if (!is_array($data) || !isset($data['id'])) {
        error_log("Erro ao formatar mensagem: {$message->body}");
        $message->nack(false);
        return;
    }

    try {
        $updateMsgToProcessed = $pdo->prepare("UPDATE notifications SET status = 'PROCESSED' WHERE id = ?");
        $updateMsgToProcessed->execute([$data['id']]);

        echo "ID da mensagem processada: {$data['id']}\n";
        $message->ack();
    } catch (\Throwable $e) {
        error_log("Falha ao atualizar o ID {$data['id']}: " . $e->getMessage());
        $message->nack(true);
    }
};

$rabbitmq->consume($consumerCallback);
$rabbitmq->close();