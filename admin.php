<?php
$admin_login = 'admin';
$admin_password = 'admin123';

if (!isset($_SERVER['PHP_AUTH_USER']) || 
    $_SERVER['PHP_AUTH_USER'] != $admin_login || 
    $_SERVER['PHP_AUTH_PW'] != $admin_password) {
    
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    echo '<!DOCTYPE html><html><head><title>Доступ запрещен</title><style>body{background:#800020;display:flex;justify-content:center;align-items:center;height:100vh;margin:0}.error-box{background:white;padding:40px;border-radius:20px;text-align:center}</style></head><body><div class="error-box"><h1>🔒 Доступ запрещен</h1><button onclick="window.location.reload()">Попробовать снова</button></div></body></html>';
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

$users = $pdo->query("SELECT a.*, GROUP_CONCAT(cm.name SEPARATOR ', ') as cars_names FROM applications a LEFT JOIN application_cars ac ON a.id = ac.application_id LEFT JOIN car_models cm ON ac.car_id = cm.id GROUP BY a.id ORDER BY a.id DESC")->fetchAll();
$total_users = count($users);
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Админ-панель</title><style>body{background:#800020;font-family:Arial;padding:20px}.container{max-width:1400px;margin:0 auto}.admin-header{background:#9E9E9E;color:#800020;padding:20px;border-radius:20px;margin-bottom:30px;display:flex;justify-content:space-between}.section{background:white;border-radius:20px;padding:25px;margin-bottom:30px}.section h2{color:#800020;border-bottom:2px solid #9E9E9E;padding-bottom:10px}table{width:100%;border-collapse:collapse}th,td{padding:12px;text-align:left;border-bottom:1px solid #e0e0e0}th{background:#f8f9fa;color:#800020}.back-link{display:inline-block;margin-top:20px;color:#800020}.logout-btn{background:rgba(128,0,32,0.8);color:white;padding:8px 20px;border-radius:10px;text-decoration:none}</style></head>
<body><div class="container"><div class="admin-header"><h1>👑 Админ-панель</h1><div><span>admin</span><a href="#" onclick="logout()" class="logout-btn">Выйти</a></div></div><div class="section"><h2>📊 Статистика</h2><p>Всего заявок: <?= $total_users ?></p></div><div class="section"><h2>📋 Заявки</h2><?php if(empty($users)): ?><p>Нет заявок</p><?php else: ?><table><thead><tr><th>ID</th><th>ФИО</th><th>Телефон</th><th>Email</th><th>Автомобили</th></tr></thead><tbody><?php foreach($users as $user): ?><tr><td><?= $user['id'] ?></td><td><?= htmlspecialchars($user['full_name']) ?></td><td><?= htmlspecialchars($user['phone']) ?></td><td><?= htmlspecialchars($user['email']) ?></td><td><?= htmlspecialchars($user['cars_names'] ?: '-') ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?><a href="index.php" class="back-link">← На сайт</a></div></div><script>function logout(){if(confirm('Выйти?')){fetch(window.location.href,{headers:{'Authorization':'Basic '+btoa('logout:logout')}}).then(()=>{window.location.href=window.location.href}).catch(()=>{window.location.reload()});}}</script></body></html>
