<?php

use Notefyer\RabbitMQ;
use Notefyer\Cache;

require_once __DIR__ . '/../vendor/autoload.php';
header('Content-Type: application/json; charset=utf-8');
const CACHE_KEY = 'notifications:list';
class NotificationAPI
{
    private PDO $pdo;
    private Cache $cache;

    public function __construct() {
        $host = getenv('DB_HOST');
        $db   = getenv('MYSQL_DATABASE');
        $user = getenv('MYSQL_USER');
        $pass = getenv('MYSQL_PASSWORD');

        $this->pdo = new PDO(
            "mysql:host=$host;dbname=$db;charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $this->cache = new Cache(
            getenv('MEMCACHED_HOST'),
            (int)getenv('MEMCACHED_PORT')
        );
    }

    public function getNotifications(): array
    {
            try {
                return $this->cache->remember(CACHE_KEY, 5, function () {
                        return $this->pdo->query("SELECT * FROM notifications ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
                });
            } catch (PDOException $e) {
                error_log($e->getMessage());
                http_response_code(500);
                return ['error' => 'Falha ao buscar notificações!'];
            }
    }

    public function clearNotifications(): array
    {
        try {
            $deleteNotifications = $this->pdo->query("DELETE FROM notifications");
            $this->cache->forget(CACHE_KEY);
            return ['success' => true, 'deleted' => $deleteNotifications->rowCount()];
        } catch (PDOException $e) {
            error_log($e->getMessage());
            http_response_code(500);
            return ['error' => 'Falha ao remover notificações!'];
        }
    }

    private function requestOperations(array $data): void
    {
          switch ($_SERVER['REQUEST_METHOD'])  {
              case 'POST':
                  $email = $data['email'] ?? null;
                  $message = $data['message'] ?? null;
                  self::throwErrorForEmptyEmailOrMessage($email, $message);
                  echo json_encode($this->createNotification($email, $message));
                  break;
              case 'GET':
                  echo json_encode($this->getNotifications());
                  break;
              case 'DELETE':
                  echo json_encode($this->clearNotifications());
                  break;
              default:
                  http_response_code(405);
                  echo json_encode(['error' => 'Método não permitido!']);
          }
    }

    public function createNotification(string $email, string $message): array
    {
        try {
            $insertNotifications = $this->pdo->prepare("INSERT INTO notifications (email, message, status) VALUES (?, ?, 'PENDING')");
            $insertNotifications->execute([$email, $message]);
            $notificationId = $this->pdo->lastInsertId();

            $rabbitmq = new RabbitMQ(
                getenv('RABBITMQ_HOST'),
                (int)getenv('RABBITMQ_PORT'),
                getenv('RABBITMQ_USER'),
                getenv('RABBITMQ_PASSWORD'),
                getenv('RABBITMQ_QUEUE')
            );

            try {
                $rabbitmq->publish([
                    'id' => $notificationId,
                    'email' => $email,
                    'message' => $message
                ]);
            } finally {
                $rabbitmq->close();
            }

            $this->cache->forget(CACHE_KEY);
            return ['success' => true, 'id' => $notificationId];
        } catch (\Throwable $e) {
            error_log($e->getMessage());
            http_response_code(500);
            return ['error' => 'Falha ao salvar notificação!'];
        }
    }

    private static function throwErrorForEmptyEmailOrMessage(string $email, string $message): void
    {
        if (!$email || !$message) {
            http_response_code(400);
            echo json_encode(['error' => 'Email e mensagem são obrigatórios!']);
            exit;
        }
    }

    public function handleRequests(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $this->requestOperations($data ?? []);
        } catch (RuntimeException $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }
}

try {
    $api = new NotificationAPI();
    $api->handleRequests();
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Falha ao conectar ao banco de dados!']);
}