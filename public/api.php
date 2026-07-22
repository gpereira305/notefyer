<?php
header('Content-Type: application/json');

$host = 'db';
$db   = 'notefyer_db';
$user = 'notefyer_user';
$pass = 'notefyer_password';



try {
   $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);

   // POST: Save new notification                                                                               
   if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $data = json_decode(file_get_contents('php://input'), true);

      $stmt = $pdo->prepare("INSERT INTO notifications (email, message) VALUES (?, ?)");
      $stmt->execute([$data['email'], $data['message']]);

      echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
      exit;
   }

   // GET: Fetch all notifications                                                                              
   if ($_SERVER['REQUEST_METHOD'] === 'GET') {
      $stmt = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC");
      $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

      echo json_encode($notifications);
      exit;
   }
} catch (PDOException $e) {
   echo json_encode(['error' => $e->getMessage()]);
}
