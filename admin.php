<?php
// HTTP BASIC AUTHENTICATION
$admin_login = 'admin';
$admin_password = 'admin123';

if (!isset($_SERVER['PHP_AUTH_USER']) || 
    $_SERVER['PHP_AUTH_USER'] != $admin_login || 
    $_SERVER['PHP_AUTH_PW'] != $admin_password) {
    
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    echo '<!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <title>Доступ запрещен</title>
        <style>
            body { font-family: Arial, sans-serif; background: #800020; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .error-box { background: white; padding: 40px; border-radius: 20px; text-align: center; }
            .error-box h1 { color: #800020; }
            .error-box button { background: #9E9E9E; color: #800020; border: none; padding: 10px 30px; border-radius: 10px; cursor: pointer; }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h1>🔒 Доступ запрещен</h1>
            <p>Требуется авторизация</p>
            <button onclick="window.location.reload()">Попробовать снова</button>
        </div>
    </body>
    </html>';
    exit;
}

header('Content-Type: text/html; charset=UTF-8');

// Подключение к БД
$db_user = 'u82464';
$db_pass = '8104996';
$db_name = 'u82464';
$db_host = 'localhost';

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8",
        $db_user,
        $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch(PDOException $e) {
    die('Ошибка БД: ' . $e->getMessage());
}

// Получаем данные для статистики
$users = $pdo->query("
    SELECT a.*, GROUP_CONCAT(cm.name SEPARATOR ', ') as cars_names
    FROM applications a
    LEFT JOIN application_cars ac ON a.id = ac.application_id
    LEFT JOIN car_models cm ON ac.car_id = cm.id
    GROUP BY a.id
    ORDER BY a.id DESC
")->fetchAll();

$stats = $pdo->query("
    SELECT cm.name, COUNT(ac.application_id) as count
    FROM car_models cm
    LEFT JOIN application_cars ac ON cm.id = ac.car_id
    GROUP BY cm.id
    ORDER BY count DESC
")->fetchAll();

$total_users = count($users);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель AutoElite</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #800020; font-family: 'Segoe UI', sans-serif; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        .admin-header { background: #9E9E9E; color: #800020; padding: 20px 30px; border-radius: 20px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .section { background: white; border-radius: 20px; padding: 25px; margin-bottom: 30px; }
        .section h2 { color: #800020; margin-bottom: 20px; border-bottom: 2px solid #9E9E9E; padding-bottom: 10px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: linear-gradient(135deg, #9E9E9E 0%, #757575 100%); padding: 20px; border-radius: 15px; text-align: center; }
        .stat-card h3 { color: #800020; margin-bottom: 10px; }
        .stat-number { font-size: 2.5em; font-weight: bold; color: #800020; }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e0e0e0; }
        th { background: #f8f9fa; color: #800020; }
        tr:hover { background: #f8f9fa; }
        .back-link { display: inline-block; margin-top: 20px; color: #800020; text-decoration: none; font-weight: 600; }
        .back-link:hover { text-decoration: underline; }
        .logout-btn { background: rgba(128, 0, 32, 0.8); color: white; padding: 8px 20px; border-radius: 10px; text-decoration: none; }
        @media (max-width: 768px) { th, td { padding: 8px; font-size: 0.85em; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="admin-header">
            <h1>👑 Панель администратора AutoElite</h1>
            <div class="admin-info">
                <span>👤 admin</span>
                <a href="#" onclick="logout()" class="logout-btn">🚪 Выйти</a>
            </div>
        </div>

        <div class="section">
            <h2>📊 Статистика заявок</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Всего заявок</h3>
                    <div class="stat-number"><?= $total_users ?></div>
                </div>
                <?php foreach ($stats as $stat): ?>
                    <div class="stat-card">
                        <h3><?= htmlspecialchars($stat['name']) ?></h3>
                        <div class="stat-number"><?= $stat['count'] ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="section">
            <h2>📋 Список заявок</h2>
            <?php if (empty($users)): ?>
                <p style="text-align: center; color: #999; padding: 40px;">Нет заявок</p>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr><th>ID</th><th>ФИО</th><th>Телефон</th><th>Email</th><th>Дата рождения</th><th>Пол</th><th>Автомобили</th><th>Пожелания</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= $user['id'] ?></td>
                                <td><?= htmlspecialchars($user['full_name']) ?></td>
                                <td><?= htmlspecialchars($user['phone']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><?= htmlspecialchars($user['birth_date']) ?></td>
                                <td><?= $user['gender'] == 'male' ? 'Мужской' : ($user['gender'] == 'female' ? 'Женский' : 'Другой') ?></td>
                                <td><?= htmlspecialchars($user['cars_names'] ?: '-') ?></td>
                                <td><?= htmlspecialchars(mb_substr($user['wish'] ?? '', 0, 50)) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <a href="index.php" class="back-link">← Вернуться на сайт</a>
        </div>
    </div>

    <script>
        function logout() {
            if (confirm('Выйти из админ-панели?')) {
                fetch(window.location.href, {
                    headers: { 'Authorization': 'Basic ' + btoa('logout:logout') }
                }).then(() => { window.location.href = window.location.href; })
                  .catch(() => { window.location.reload(); });
            }
        }
    </script>
</body>
</html>
