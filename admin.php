<?php
session_start();

header('Content-Type: text/html; charset=UTF-8');

// --------------------
// ПОДКЛЮЧЕНИЕ К БД
// --------------------

$db_user = 'u82464';
$db_pass = '8104996';
$db_name = 'u82464';
$db_host = 'localhost';

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch(PDOException $e) {
    die('Ошибка подключения к базе данных: ' . $e->getMessage());
}

// --------------------
// СОЗДАНИЕ ТАБЛИЦЫ АДМИНОВ (если не существует)
// --------------------

$pdo->exec("
    CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// --------------------
// СОЗДАНИЕ ПЕРВОГО АДМИНА (если нет ни одного)
// --------------------

$stmt = $pdo->query("SELECT COUNT(*) as count FROM admins");
$adminCount = $stmt->fetch()['count'];

if ($adminCount == 0) {
    $default_password = 'admin123';
    $default_hash = password_hash($default_password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?)");
    $stmt->execute(['admin', $default_hash]);
    
    // Сохраняем сообщение о создании админа
    $_SESSION['admin_created'] = true;
    $_SESSION['admin_created_login'] = 'admin';
    $_SESSION['admin_created_password'] = $default_password;
}

// --------------------
// АВТОРИЗАЦИЯ
// --------------------

$auth_error = '';
$success_message = '';

// Показываем сообщение о создании админа
if (isset($_SESSION['admin_created'])) {
    $success_message = "✅ Первый администратор создан!<br>
        📝 Логин: <strong>" . htmlspecialchars($_SESSION['admin_created_login']) . "</strong><br>
        🔑 Пароль: <strong>" . htmlspecialchars($_SESSION['admin_created_password']) . "</strong><br>
        ⚠️ Сохраните эти данные!";
    unset($_SESSION['admin_created']);
    unset($_SESSION['admin_created_login']);
    unset($_SESSION['admin_created_password']);
}

// Выход из админ-панели
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit();
}

// Проверка авторизации
if (!isset($_SESSION['admin_id'])) {
    // Если отправлена форма входа
    if (isset($_POST['login_submit'])) {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        
        if (empty($username) || empty($password)) {
            $auth_error = 'Введите логин и пароль';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();
            
            if ($admin && password_verify($password, $admin['password_hash'])) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                header('Location: admin.php');
                exit;
            } else {
                $auth_error = 'Неверный логин или пароль';
            }
        }
    }
    
    // Показываем форму входа
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Вход в админ-панель</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: #800020;
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                padding: 20px;
            }

            .login-container {
                max-width: 450px;
                width: 100%;
                background: white;
                border-radius: 20px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                overflow: hidden;
                animation: slideIn 0.5s ease-out;
            }

            @keyframes slideIn {
                from {
                    opacity: 0;
                    transform: translateY(-30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .login-header {
                background: #9E9E9E;
                color: #800020;
                padding: 30px;
                text-align: center;
            }

            .login-header h1 {
                font-size: 1.8em;
                margin-bottom: 10px;
            }

            .login-header p {
                opacity: 0.9;
                font-size: 0.95em;
            }

            .login-content {
                padding: 40px;
            }

            .form-group {
                margin-bottom: 25px;
            }

            label {
                display: block;
                margin-bottom: 8px;
                font-weight: 600;
                color: #333;
                font-size: 0.95em;
            }

            input {
                width: 100%;
                padding: 12px 15px;
                border: 2px solid #e0e0e0;
                border-radius: 10px;
                font-size: 1em;
                transition: all 0.3s ease;
            }

            input:focus {
                outline: none;
                border-color: #9E9E9E;
                box-shadow: 0 0 0 3px rgba(158, 158, 158, 0.3);
            }

            .btn-submit {
                background: #9E9E9E;
                color: #800020;
                border: none;
                padding: 14px 30px;
                font-size: 1em;
                font-weight: 600;
                border-radius: 10px;
                cursor: pointer;
                transition: all 0.3s ease;
                width: 100%;
            }

            .btn-submit:hover {
                background: #757575;
                transform: translateY(-2px);
                box-shadow: 0 5px 20px rgba(128, 0, 32, 0.4);
            }

            .error-message {
                background: #f8d7da;
                color: #721c24;
                padding: 12px;
                border-radius: 10px;
                margin-bottom: 20px;
                border-left: 4px solid #dc3545;
            }

            .success-message {
                background: #d4edda;
                color: #155724;
                padding: 12px;
                border-radius: 10px;
                margin-bottom: 20px;
                border-left: 4px solid #28a745;
            }

            .info-box {
                background: #d1ecf1;
                color: #0c5460;
                padding: 12px;
                border-radius: 10px;
                margin-bottom: 20px;
                text-align: center;
                font-size: 0.9em;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-header">
                <h1>🔐 Админ-панель</h1>
                <p>Вход для администраторов</p>
            </div>
            <div class="login-content">
                <?php if ($success_message): ?>
                    <div class="success-message"><?= $success_message ?></div>
                <?php endif; ?>
                
                <?php if ($auth_error): ?>
                    <div class="error-message">❌ <?= $auth_error ?></div>
                <?php endif; ?>
                
                <div class="info-box">
                    ℹ️ Введите логин и пароль администратора
                </div>
                
                <form method="POST">
                    <div class="form-group">
                        <label>Логин</label>
                        <input type="text" name="username" required autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label>Пароль</label>
                        <input type="password" name="password" required>
                    </div>
                    
                    <button type="submit" name="login_submit" class="btn-submit">Войти</button>
                </form>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// --------------------
// ОБРАБОТКА ДЕЙСТВИЙ АДМИНА
// --------------------

$message = '';
$message_type = '';

// Создание нового администратора
if (isset($_POST['create_admin'])) {
    $new_username = trim($_POST['new_username'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    
    if (empty($new_username) || empty($new_password)) {
        $message = 'Заполните все поля';
        $message_type = 'error';
    } elseif (strlen($new_password) < 4) {
        $message = 'Пароль должен быть не менее 4 символов';
        $message_type = 'error';
    } else {
        try {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?)");
            $stmt->execute([$new_username, $hash]);
            $message = "✅ Администратор '{$new_username}' успешно создан!";
            $message_type = 'success';
        } catch(PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $message = "❌ Администратор с логином '{$new_username}' уже существует";
            } else {
                $message = "❌ Ошибка: " . $e->getMessage();
            }
            $message_type = 'error';
        }
    }
}

// Удаление администратора
if (isset($_GET['delete_admin']) && is_numeric($_GET['delete_admin'])) {
    $delete_id = (int)$_GET['delete_admin'];
    
    // Нельзя удалить самого себя
    if ($delete_id == $_SESSION['admin_id']) {
        $message = "❌ Нельзя удалить самого себя!";
        $message_type = 'error';
    } else {
        $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
        $stmt->execute([$delete_id]);
        $message = "✅ Администратор удален";
        $message_type = 'success';
    }
}

// Получение ID пользователя для редактирования/удаления
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$delete_id = isset($_GET['delete']) ? (int)$_GET['delete'] : null;

// Удаление пользователя (из applications)
if ($delete_id && isset($_GET['confirm']) && $_GET['confirm'] == 'yes') {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?");
        $stmt->execute([$delete_id]);
        
        $stmt = $pdo->prepare("DELETE FROM applications WHERE id = ?");
        $stmt->execute([$delete_id]);
        
        $pdo->commit();
        $message = "✅ Пользователь успешно удален!";
        $message_type = "success";
    } catch(PDOException $e) {
        $pdo->rollBack();
        $message = "❌ Ошибка при удалении: " . $e->getMessage();
        $message_type = "error";
    }
}

// Редактирование пользователя
$edit_user_data = null;
if ($edit_id) {
    $stmt = $pdo->prepare("
        SELECT a.*, GROUP_CONCAT(al.language_id) as language_ids
        FROM applications a
        LEFT JOIN application_languages al ON a.id = al.application_id
        WHERE a.id = ?
        GROUP BY a.id
    ");
    $stmt->execute([$edit_id]);
    $edit_user_data = $stmt->fetch();
    
    if ($edit_user_data && $edit_user_data['language_ids']) {
        $edit_user_data['languages'] = explode(',', $edit_user_data['language_ids']);
    } else {
        $edit_user_data['languages'] = [];
    }
}

// Обновление данных пользователя
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user'])) {
    $user_id = (int)$_POST['user_id'];
    $errors = [];
    
    if (empty($_POST['full_name']) || !preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u', $_POST['full_name'])) {
        $errors['full_name'] = 'ФИО обязательно и может содержать только буквы, пробелы и дефисы.';
    }
    
    if (empty($_POST['phone']) || !preg_match('/^(\+7|8)?[\s\-]?\(?[0-9]{3}\)?[\s\-]?[0-9]{3}[\s\-]?[0-9]{2}[\s\-]?[0-9]{2}$/', $_POST['phone'])) {
        $errors['phone'] = 'Введите корректный номер телефона.';
    }
    
    if (empty($_POST['email']) || !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный e-mail.';
    }
    
    if (empty($_POST['birth_date'])) {
        $errors['birth_date'] = 'Выберите дату рождения.';
    }
    
    if (empty($_POST['gender']) || !in_array($_POST['gender'], ['male', 'female', 'other'])) {
        $errors['gender'] = 'Выберите пол.';
    }
    
    $selectedLangs = $_POST['languages'] ?? [];
    if (empty($selectedLangs)) {
        $errors['languages'] = 'Выберите хотя бы один язык.';
    }
    
    if (!isset($_POST['contract'])) {
        $errors['contract'] = 'Необходимо принять условия.';
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                UPDATE applications 
                SET full_name = ?, phone = ?, email = ?, birth_date = ?, 
                    gender = ?, bio = ?, contract_accepted = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['full_name'],
                $_POST['phone'],
                $_POST['email'],
                $_POST['birth_date'],
                $_POST['gender'],
                $_POST['bio'],
                1,
                $user_id
            ]);
            
            $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")->execute([$user_id]);
            $stmtLang = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
            foreach ($selectedLangs as $langId) {
                $stmtLang->execute([$user_id, $langId]);
            }
            
            $pdo->commit();
            $message = "✅ Данные пользователя успешно обновлены!";
            $message_type = "success";
            
            header("Location: admin.php?message=" . urlencode($message));
            exit();
        } catch(PDOException $e) {
            $pdo->rollBack();
            $message = "❌ Ошибка при обновлении: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Получаем сообщение из URL
if (isset($_GET['message'])) {
    $message = $_GET['message'];
    $message_type = "success";
}

// --------------------
// ПОЛУЧЕНИЕ ДАННЫХ
// --------------------

// Список языков программирования
$languagesList = $pdo->query("
    SELECT id, name 
    FROM programming_languages 
    ORDER BY name
")->fetchAll();

$allowedLanguageIds = array_column($languagesList, 'id');

// Получаем всех пользователей
$users = $pdo->query("
    SELECT a.*, 
           GROUP_CONCAT(pl.name SEPARATOR ', ') as languages_names
    FROM applications a
    LEFT JOIN application_languages al ON a.id = al.application_id
    LEFT JOIN programming_languages pl ON al.language_id = pl.id
    GROUP BY a.id
    ORDER BY a.id DESC
")->fetchAll();

// Получаем статистику по языкам
$stats = $pdo->query("
    SELECT pl.id, pl.name, COUNT(al.application_id) as count
    FROM programming_languages pl
    LEFT JOIN application_languages al ON pl.id = al.language_id
    GROUP BY pl.id, pl.name
    ORDER BY count DESC
")->fetchAll();

// Получаем список администраторов
$admins = $pdo->query("SELECT id, username, created_at FROM admins ORDER BY id")->fetchAll();

$total_users = count($users);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель администратора</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #800020;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Header */
        .admin-header {
            background: #9E9E9E;
            color: #800020;
            padding: 20px 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        .admin-header h1 {
            font-size: 1.8em;
        }

        .admin-info {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .logout-btn {
            background: rgba(128, 0, 32, 0.8);
            color: white;
            padding: 8px 20px;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: #800020;
            transform: translateY(-2px);
        }

        /* Messages */
        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        /* Sections */
        .section {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .section h2 {
            color: #800020;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #9E9E9E;
        }

        .section h3 {
            color: #800020;
            margin-bottom: 15px;
            margin-top: 10px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .stat-card {
            background: linear-gradient(135deg, #9E9E9E 0%, #757575 100%);
            color: #800020;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card h3 {
            color: #800020;
            margin-bottom: 10px;
            opacity: 0.9;
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #800020;
        }

        /* Forms */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1em;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #9E9E9E;
            box-shadow: 0 0 0 3px rgba(158, 158, 158, 0.3);
        }

        select[multiple] {
            height: 120px;
        }

        .btn-submit, .btn-create {
            background: #9E9E9E;
            color: #800020;
            border: none;
            padding: 12px 30px;
            font-size: 1em;
            font-weight: 600;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-submit:hover, .btn-create:hover {
            background: #757575;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(128, 0, 32, 0.4);
        }

        .btn-edit, .btn-delete {
            padding: 5px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9em;
            transition: all 0.3s;
            display: inline-block;
        }

        .btn-edit {
            background: #4CAF50;
            color: white;
        }

        .btn-edit:hover {
            background: #45a049;
        }

        .btn-delete {
            background: #f44336;
            color: white;
        }

        .btn-delete:hover {
            background: #da190b;
        }

        .btn-small {
            padding: 3px 8px;
            font-size: 0.8em;
        }

        /* Tables */
        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        th {
            background: #f8f9fa;
            color: #800020;
            font-weight: 600;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* Admin row */
        .admin-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #e0e0e0;
        }

        .admin-row:last-child {
            border-bottom: none;
        }

        .admin-info {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        /* Modal */
        .modal {
            display: <?= $edit_user_data ? 'flex' : 'none' ?>;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 30px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #9E9E9E;
        }

        .modal-header h3 {
            color: #800020;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5em;
            cursor: pointer;
            color: #999;
        }

        .close-btn:hover {
            color: #800020;
        }

        /* Create admin form */
        .create-admin-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 15px;
            margin-top: 15px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            th, td {
                padding: 8px;
                font-size: 0.85em;
            }
            
            .admin-header {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="admin-header">
            <h1>👑 Панель администратора</h1>
            <div class="admin-info">
                <span>👤 <?= htmlspecialchars($_SESSION['admin_username']) ?></span>
                <a href="?logout=1" class="logout-btn">🚪 Выйти</a>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="message <?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Section -->
        <div class="section">
            <h2>📊 Статистика по языкам программирования</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Всего пользователей</h3>
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

        <!-- Admins Management Section -->
        <div class="section">
            <h2>👥 Управление администраторами</h2>
            
            <!-- Create new admin -->
            <div class="create-admin-form">
                <h3>➕ Создать нового администратора</h3>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Логин</label>
                            <input type="text" name="new_username" placeholder="Введите логин" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Пароль</label>
                            <input type="text" name="new_password" placeholder="Введите пароль" required>
                        </div>
                        <button type="submit" name="create_admin" class="btn-create">➕ Создать</button>
                    </div>
                </form>
            </div>
            
            <!-- Admins list -->
            <?php if (!empty($admins)): ?>
                <h3 style="margin-top: 25px;">📋 Список администраторов</h3>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Логин</th>
                                <th>Дата создания</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admins as $admin): ?>
                                <tr>
                                    <td><?= $admin['id'] ?></td>
                                    <td><?= htmlspecialchars($admin['username']) ?>
                                        <?= $admin['id'] == $_SESSION['admin_id'] ? ' <span style="color:green;">(вы)</span>' : '' ?>
                                    </td>
                                    <td><?= $admin['created_at'] ?></td>
                                    <td>
                                        <?php if ($admin['id'] != $_SESSION['admin_id']): ?>
                                            <a href="?delete_admin=<?= $admin['id'] ?>" class="btn-delete btn-small" 
                                               onclick="return confirm('Удалить администратора «<?= htmlspecialchars($admin['username']) ?>»?')">🗑️ Удалить</a>
                                        <?php else: ?>
                                            <span style="color:#999;">текущий</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Users Section -->
        <div class="section">
            <h2>👥 Все пользователи</h2>
            <?php if (empty($users)): ?>
                <p style="text-align: center; color: #999; padding: 40px;">Нет зарегистрированных пользователей</p>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>ФИО</th>
                                <th>Телефон</th>
                                <th>Email</th>
                                <th>Дата рождения</th>
                                <th>Пол</th>
                                <th>Языки</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= $user['id'] ?></td>
                                    <td><?= htmlspecialchars($user['full_name']) ?></td>
                                    <td><?= htmlspecialchars($user['phone']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= htmlspecialchars($user['birth_date']) ?></td>
                                    <td>
                                        <?php
                                        $genders = ['male' => 'Мужской', 'female' => 'Женский', 'other' => 'Другой'];
                                        echo $genders[$user['gender']] ?? $user['gender'];
                                        ?>
                                    </td>
                                    <td><?= htmlspecialchars($user['languages_names'] ?: '-') ?></td>
                                    <td class="actions">
                                        <a href="?edit=<?= $user['id'] ?>" class="btn-edit">✏️ Редакт.</a>
                                        <a href="?delete=<?= $user['id'] ?>" class="btn-delete" 
                                           onclick="return confirmDelete(event, <?= $user['id'] ?>, '<?= htmlspecialchars(addslashes($user['full_name'])) ?>')">🗑️ Удалить</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Modal -->
    <?php if ($edit_user_data && $languagesList): ?>
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>✏️ Редактирование пользователя #<?= $edit_user_data['id'] ?></h3>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="user_id" value="<?= $edit_user_data['id'] ?>">
                
                <div class="form-group">
                    <label>ФИО *</label>
                    <input type="text" name="full_name" value="<?= htmlspecialchars($edit_user_data['full_name']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Телефон *</label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($edit_user_data['phone']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($edit_user_data['email']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Дата рождения *</label>
                    <input type="date" name="birth_date" value="<?= htmlspecialchars($edit_user_data['birth_date']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Пол *</label>
                    <select name="gender" required>
                        <option value="male" <?= $edit_user_data['gender'] == 'male' ? 'selected' : '' ?>>Мужской</option>
                        <option value="female" <?= $edit_user_data['gender'] == 'female' ? 'selected' : '' ?>>Женский</option>
                        <option value="other" <?= $edit_user_data['gender'] == 'other' ? 'selected' : '' ?>>Другой</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Любимые языки *</label>
                    <select name="languages[]" multiple required>
                        <?php foreach ($languagesList as $lang): ?>
                            <option value="<?= $lang['id'] ?>" <?= in_array($lang['id'], $edit_user_data['languages']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($lang['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Биография</label>
                    <textarea name="bio" rows="4"><?= htmlspecialchars($edit_user_data['bio']) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="contract" value="1" <?= $edit_user_data['contract_accepted'] ? 'checked' : '' ?> required>
                        Согласие на обработку данных *
                    </label>
                </div>
                
                <button type="submit" name="update_user" class="btn-submit">💾 Сохранить изменения</button>
            </form>
        </div>
    </div>
    
    <script>
        function closeModal() {
            window.location.href = 'admin.php';
        }
        
        document.getElementById('editModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
    <?php endif; ?>

    <script>
        function confirmDelete(event, userId, userName) {
            event.preventDefault();
            if (confirm(`Вы уверены, что хотите удалить пользователя "${userName}"? Это действие нельзя отменить.`)) {
                window.location.href = `?delete=${userId}&confirm=yes`;
            }
            return false;
        }
    </script>
</body>
</html>
