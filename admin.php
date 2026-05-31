<?php
$admin_login = 'admin';
$admin_password = 'admin123';

if (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] != $admin_login || $_SERVER['PHP_AUTH_PW'] != $admin_password) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    echo '<!DOCTYPE html><html><head><title>Доступ запрещен</title><style>body{background:#800020;display:flex;justify-content:center;align-items:center;height:100vh}.error-box{background:white;padding:40px;border-radius:20px;text-align:center}</style></head><body><div class="error-box"><h1>🔒 Доступ запрещен</h1><button onclick="window.location.reload()">Попробовать снова</button></div></body></html>';
    exit;
}

$db_user = 'u82464';
$db_pass = '8104996';
$db_name = 'u82464';
$db_host = 'localhost';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch(PDOException $e) {
    die('Ошибка БД: ' . $e->getMessage());
}

// Создание таблиц (упрощенная версия)
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
$pdo->exec("DROP TABLE IF EXISTS application_cars");
$pdo->exec("DROP TABLE IF EXISTS applications");
$pdo->exec("DROP TABLE IF EXISTS car_models");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

$pdo->exec("CREATE TABLE applications (id INT AUTO_INCREMENT PRIMARY KEY, full_name VARCHAR(255) NOT NULL, phone VARCHAR(50) NOT NULL, email VARCHAR(255) NOT NULL, birth_date DATE NOT NULL, gender ENUM('male','female','other') NOT NULL, wish TEXT, contract_accepted TINYINT DEFAULT 0, login VARCHAR(50) UNIQUE, password_hash VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$pdo->exec("CREATE TABLE car_models (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) UNIQUE NOT NULL, price VARCHAR(100) NOT NULL)");
$pdo->exec("CREATE TABLE application_cars (application_id INT NOT NULL, car_id INT NOT NULL, PRIMARY KEY (application_id, car_id), FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE, FOREIGN KEY (car_id) REFERENCES car_models(id) ON DELETE CASCADE)");

$cars = [
    ['Porsche Panamera', 'от 9 500 000 ₽'],
    ['Mercedes-Benz S-Class', 'от 12 000 000 ₽'],
    ['BMW 7 Series', 'от 8 900 000 ₽'],
    ['Audi A8', 'от 7 800 000 ₽'],
    ['Lexus LS', 'от 7 500 000 ₽'],
    ['Tesla Model S', 'от 6 500 000 ₽'],
    ['Jaguar XJ', 'от 6 200 000 ₽'],
    ['Maserati Quattroporte', 'от 10 500 000 ₽']
];
$stmt = $pdo->prepare("INSERT IGNORE INTO car_models (name, price) VALUES (?, ?)");
foreach ($cars as $car) $stmt->execute($car);

// Получаем данные для отображения
$users = $pdo->query("SELECT a.*, GROUP_CONCAT(cm.name SEPARATOR ', ') as cars_names FROM applications a LEFT JOIN application_cars ac ON a.id = ac.application_id LEFT JOIN car_models cm ON ac.car_id = cm.id GROUP BY a.id ORDER BY a.id DESC")->fetchAll();
$total_users = count($users);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Админ-панель AutoElite</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#800020;font-family:Arial,sans-serif;padding:20px}
        .container{max-width:1400px;margin:0 auto}
        .admin-header{background:#9E9E9E;color:#800020;padding:20px;border-radius:20px;margin-bottom:30px;display:flex;justify-content:space-between}
        .section{background:white;border-radius:20px;padding:25px;margin-bottom:30px}
        .section h2{color:#800020;margin-bottom:20px;border-bottom:2px solid #9E9E9E;padding-bottom:10px}
        table{width:100%;border-collapse:collapse}
        th,td{padding:12px;text-align:left;border-bottom:1px solid #e0e0e0}
        th{background:#f8f9fa;color:#800020}
        tr:hover{background:#f8f9fa}
        .btn-edit{background:#4CAF50;color:white;padding:5px 12px;border-radius:5px;text-decoration:none}
        .btn-delete{background:#f44336;color:white;padding:5px 12px;border-radius:5px;text-decoration:none}
        .back-link{display:inline-block;margin-top:20px;color:#800020}
    </style>
</head>
<body>
    <div class="container">
        <div class="admin-header">
            <h1>👑 Админ-панель AutoElite</h1>
            <div>👤 admin</div>
        </div>
        <div class="section">
            <h2>📊 Статистика</h2>
            <p>Всего заявок: <?= $total_users ?></p>
        </div>
        <div class="section">
            <h2>📋 Все заявки</h2>
            <table>
                <thead><tr><th>ID</th><th>ФИО</th><th>Телефон</th><th>Email</th><th>Автомобили</th></tr></thead>
                <tbody>
                    <?php foreach($users as $user): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td><?= htmlspecialchars($user['full_name']) ?></td>
                        <td><?= htmlspecialchars($user['phone']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars($user['cars_names'] ?: '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <a href="index.php" class="back-link">← На сайт</a>
        </div>
    </div>
</body>
</html>
