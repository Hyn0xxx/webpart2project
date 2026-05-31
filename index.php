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
// ПРОВЕРКА И СОЗДАНИЕ ТАБЛИЦ
// --------------------

// Проверяем, существует ли таблица applications
$tableExists = $pdo->query("SHOW TABLES LIKE 'applications'")->rowCount() > 0;

if (!$tableExists) {
    // Создаем таблицу заявок
    $pdo->exec("
        CREATE TABLE applications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(255) NOT NULL,
            phone VARCHAR(50) NOT NULL,
            email VARCHAR(255) NOT NULL,
            birth_date DATE NOT NULL,
            gender ENUM('male', 'female', 'other') NOT NULL,
            wish TEXT,
            contract_accepted TINYINT(1) DEFAULT 0,
            login VARCHAR(50) UNIQUE,
            password_hash VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Создаем таблицу автомобилей
    $pdo->exec("
        CREATE TABLE car_models (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) UNIQUE NOT NULL,
            price VARCHAR(100) NOT NULL
        )
    ");
    
    // Создаем таблицу связи
    $pdo->exec("
        CREATE TABLE application_cars (
            id INT AUTO_INCREMENT PRIMARY KEY,
            application_id INT NOT NULL,
            car_id INT NOT NULL,
            INDEX(application_id),
            INDEX(car_id)
        )
    ");
    
    // Добавляем автомобили
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
    
    $stmt = $pdo->prepare("INSERT INTO car_models (name, price) VALUES (?, ?)");
    foreach ($cars as $car) {
        $stmt->execute($car);
    }
}

// --------------------
// ФУНКЦИИ
// --------------------

function generateLogin() {
    return 'user_' . bin2hex(random_bytes(4));
}

function generatePassword($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

// --------------------
// СПИСОК АВТОМОБИЛЕЙ
// --------------------

$carsList = $pdo->query("SELECT id, name, price FROM car_models ORDER BY name")->fetchAll();
$allowedCarIds = array_column($carsList, 'id');

// --------------------
// ВЫХОД
// --------------------

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit();
}

// --------------------
// АВТОРИЗАЦИЯ
// --------------------

$messages = [];
$loginError = '';

if (isset($_POST['login_submit'])) {
    $login = trim($_POST['login'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($login)) {
        $loginError = 'Введите логин';
    }
    elseif (empty($password)) {
        $loginError = 'Введите пароль';
    }
    else {
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            header('Location: index.php');
            exit();
        } else {
            $loginError = 'Неверный логин или пароль';
        }
    }
}

// --------------------
// ОБРАБОТКА ФОРМЫ
// --------------------

$justSaved = false;

// REST API endpoint для AJAX
if ($_SERVER['REQUEST_METHOD'] == 'POST' && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($input) {
        $errors = [];
        
        // Валидация
        if (empty($input['full_name']) || !preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u', $input['full_name'])) {
            $errors['full_name'] = 'ФИО обязательно и может содержать только буквы, пробелы и дефисы.';
        }
        
        if (empty($input['phone']) || !preg_match('/^(\+7|8)?[\s\-]?\(?[0-9]{3}\)?[\s\-]?[0-9]{3}[\s\-]?[0-9]{2}[\s\-]?[0-9]{2}$/', $input['phone'])) {
            $errors['phone'] = 'Введите корректный номер телефона.';
        }
        
        if (empty($input['email']) || !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Введите корректный e-mail.';
        }
        
        if (empty($input['birth_date'])) {
            $errors['birth_date'] = 'Выберите дату рождения.';
        }
        
        if (empty($input['gender']) || !in_array($input['gender'], ['male', 'female', 'other'])) {
            $errors['gender'] = 'Выберите пол.';
        }
        
        $selectedCars = $input['cars'] ?? [];
        if (empty($selectedCars)) {
            $errors['cars'] = 'Выберите хотя бы один автомобиль.';
        }
        
        if (empty($input['contract'])) {
            $errors['contract'] = 'Необходимо принять условия.';
        }
        
        if (!empty($errors)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit();
        }
        
        try {
            $pdo->beginTransaction();
            
            $isAuth = isset($_SESSION['user_id']);
            
            if ($isAuth) {
                $appId = $_SESSION['user_id'];
                $stmt = $pdo->prepare("
                    UPDATE applications
                    SET full_name=?, phone=?, email=?, birth_date=?, gender=?, wish=?, contract_accepted=?
                    WHERE id=?
                ");
                $stmt->execute([
                    $input['full_name'],
                    $input['phone'],
                    $input['email'],
                    $input['birth_date'],
                    $input['gender'],
                    $input['wish'] ?? '',
                    1,
                    $appId
                ]);
                
                $pdo->prepare("DELETE FROM application_cars WHERE application_id=?")->execute([$appId]);
                $response = ['success' => true, 'messages' => ['✅ Заявка успешно обновлена!'], 'updated' => true];
            } else {
                $login = generateLogin();
                $plainPassword = generatePassword();
                $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("
                    INSERT INTO applications (full_name, phone, email, birth_date, gender, wish, contract_accepted, login, password_hash)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $input['full_name'],
                    $input['phone'],
                    $input['email'],
                    $input['birth_date'],
                    $input['gender'],
                    $input['wish'] ?? '',
                    1,
                    $login,
                    $passwordHash
                ]);
                $appId = $pdo->lastInsertId();
                
                $response = [
                    'success' => true, 
                    'messages' => ['✅ Заявка успешно отправлена!'],
                    'credentials' => ['login' => $login, 'password' => $plainPassword]
                ];
            }
            
            $stmtCar = $pdo->prepare("INSERT INTO application_cars (application_id, car_id) VALUES (?, ?)");
            foreach ($selectedCars as $carId) {
                $stmtCar->execute([$appId, (int)$carId]);
            }
            
            $pdo->commit();
            
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
            
        } catch(PDOException $e) {
            $pdo->rollBack();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'errors' => ['db_error' => 'Ошибка БД: ' . $e->getMessage()]]);
            exit();
        }
    }
}

// Обычная POST обработка (без JS)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['login_submit'])) {
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
    
    $selectedCars = $_POST['cars'] ?? [];
    if (empty($selectedCars)) {
        $errors['cars'] = 'Выберите хотя бы один автомобиль.';
    }
    
    if (!isset($_POST['contract'])) {
        $errors['contract'] = 'Необходимо принять условия.';
    }
    
    $formValues = [
        'full_name' => $_POST['full_name'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'email' => $_POST['email'] ?? '',
        'birth_date' => $_POST['birth_date'] ?? '',
        'gender' => $_POST['gender'] ?? '',
        'wish' => $_POST['wish'] ?? '',
        'contract' => isset($_POST['contract']),
        'cars' => $selectedCars
    ];
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            if (isset($_SESSION['user_id'])) {
                $appId = $_SESSION['user_id'];
                $stmt = $pdo->prepare("
                    UPDATE applications
                    SET full_name=?, phone=?, email=?, birth_date=?, gender=?, wish=?, contract_accepted=?
                    WHERE id=?
                ");
                $stmt->execute([
                    $_POST['full_name'],
                    $_POST['phone'],
                    $_POST['email'],
                    $_POST['birth_date'],
                    $_POST['gender'],
                    $_POST['wish'],
                    1,
                    $appId
                ]);
                
                $pdo->prepare("DELETE FROM application_cars WHERE application_id=?")->execute([$appId]);
                $messages[] = '✅ Заявка успешно обновлена!';
            } else {
                $login = generateLogin();
                $plainPassword = generatePassword();
                $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("
                    INSERT INTO applications (full_name, phone, email, birth_date, gender, wish, contract_accepted, login, password_hash)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_POST['full_name'],
                    $_POST['phone'],
                    $_POST['email'],
                    $_POST['birth_date'],
                    $_POST['gender'],
                    $_POST['wish'],
                    1,
                    $login,
                    $passwordHash
                ]);
                $appId = $pdo->lastInsertId();
                
                $_SESSION['generated_login'] = $login;
                $_SESSION['generated_password'] = $plainPassword;
                $justSaved = true;
                
                $messages[] = '✅ Заявка успешно отправлена!';
            }
            
            $stmtCar = $pdo->prepare("INSERT INTO application_cars (application_id, car_id) VALUES (?, ?)");
            foreach ($selectedCars as $carId) {
                $stmtCar->execute([$appId, (int)$carId]);
            }
            
            $pdo->commit();
            
        } catch(PDOException $e) {
            $pdo->rollBack();
            $errors['db_error'] = 'Ошибка БД: ' . $e->getMessage();
        }
    }
}

// --------------------
// ЗАГРУЗКА ДАННЫХ ДЛЯ ФОРМЫ
// --------------------

if (isset($formValues)) {
    $values = $formValues;
    $errors = $errors ?? [];
} 
elseif (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM applications WHERE id=?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch();
    
    if ($userData) {
        $values['full_name'] = $userData['full_name'];
        $values['phone'] = $userData['phone'];
        $values['email'] = $userData['email'];
        $values['birth_date'] = $userData['birth_date'];
        $values['gender'] = $userData['gender'];
        $values['wish'] = $userData['wish'];
        $values['contract'] = $userData['contract_accepted'];
        
        $stmt = $pdo->prepare("SELECT car_id FROM application_cars WHERE application_id=?");
        $stmt->execute([$_SESSION['user_id']]);
        $values['cars'] = array_column($stmt->fetchAll(), 'car_id');
    }
} 
else {
    $values = [
        'full_name' => '',
        'phone' => '',
        'email' => '',
        'birth_date' => '',
        'gender' => '',
        'wish' => '',
        'contract' => false,
        'cars' => []
    ];
    $errors = [];
}

if (!empty($_SESSION['generated_login']) && $justSaved) {
    $loginMessage = "✅ Ваши данные для входа:<br><br>
        Логин: <b>" . htmlspecialchars($_SESSION['generated_login']) . "</b><br>
        Пароль: <b>" . htmlspecialchars($_SESSION['generated_password']) . "</b><br><br>
        ⚠️ Сохраните их! Теперь вы можете авторизоваться и редактировать свои данные.";
    $messages[] = $loginMessage;
    unset($_SESSION['generated_login']);
    unset($_SESSION['generated_password']);
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoElite - Премиальный автосалон</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        .order-section { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 80px 0; }
        .order-container { max-width: 800px; margin: 0 auto; background: white; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.1); overflow: hidden; }
        .order-header { background: #800020; color: white; padding: 30px; text-align: center; }
        .order-header h2 { font-size: 1.8em; margin-bottom: 10px; }
        .order-body { padding: 40px; }
        .auth-box { background: #9E9E9E; padding: 25px; border-radius: 15px; margin-bottom: 30px; }
        .auth-box h3 { color: #800020; margin-bottom: 20px; }
        .form-field { margin-bottom: 20px; }
        .form-field label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        .form-field input, .form-field select, .form-field textarea { width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 1em; transition: all 0.3s; }
        .form-field input:focus, .form-field select:focus, .form-field textarea:focus { outline: none; border-color: #9E9E9E; box-shadow: 0 0 0 3px rgba(158,158,158,0.3); }
        .form-field select[multiple] { height: 150px; }
        .radio-group { display: flex; gap: 20px; padding: 10px 0; }
        .radio-group label { display: inline-flex; align-items: center; font-weight: normal; margin-bottom: 0; cursor: pointer; }
        .radio-group input { width: auto; margin-right: 8px; }
        .checkbox-field { display: flex; align-items: center; cursor: pointer; }
        .checkbox-field input { width: auto; margin-right: 10px; }
        .btn-send { background: #800020; color: white; border: none; padding: 14px 30px; font-size: 1em; font-weight: 600; border-radius: 10px; cursor: pointer; transition: all 0.3s; width: 100%; }
        .btn-send:hover { background: #9E9E9E; color: #800020; transform: translateY(-2px); }
        .error-msg { color: #dc3545; font-size: 0.85em; margin-top: 5px; display: block; }
        .success-msg { background: #d4edda; color: #155724; padding: 15px; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid #28a745; }
        .form-error { border-color: #dc3545 !important; }
        .logout-link { display: inline-block; margin-top: 10px; color: #800020; text-decoration: none; font-weight: 600; }
        .admin-button { position: fixed; bottom: 20px; right: 20px; background: #800020; color: white; padding: 12px 20px; border-radius: 30px; font-weight: 600; box-shadow: 0 5px 15px rgba(0,0,0,0.2); transition: all 0.3s; z-index: 1000; display: flex; align-items: center; gap: 8px; cursor: pointer; border: none; }
        .admin-button:hover { background: #9E9E9E; color: #800020; transform: translateY(-3px); }
        hr { margin: 30px 0; border: none; height: 1px; background: linear-gradient(to right, transparent, #800020, transparent); }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); display: flex; justify-content: center; align-items: center; z-index: 2000; }
        .modal-overlay.hidden { display: none; }
        .modal-window { background: white; width: 90%; max-width: 450px; border-radius: 20px; padding: 30px; position: relative; animation: modalAppear 0.3s ease; }
        @keyframes modalAppear { from { opacity: 0; transform: translateY(-50px); } to { opacity: 1; transform: translateY(0); } }
        .modal-close-btn { position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 1.5rem; color: #999; cursor: pointer; }
        .modal-close-btn:hover { color: #800020; }
        .modal-title { font-size: 1.5rem; color: #800020; margin-bottom: 20px; text-align: center; }
        @media (max-width: 768px) { .order-body { padding: 20px; } .radio-group { flex-direction: column; gap: 10px; } .admin-button { padding: 8px 15px; font-size: 0.9em; } }
    </style>
</head>
<body>
    <header class="header">
        <div class="video-background">
            <video autoplay muted loop playsinline>
                <source src="assets/video/large-vecteezy_selective-focus-on-a-car-male-customer-talking-to-auto_33116350_x-large.mp4" type="video/mp4">
            </video>
            <div class="video-overlay"></div>
        </div>
        
        <nav class="navbar">
            <div class="container nav-container">
                <div class="logo">
                    <h1>AutoElite</h1>
                    <p>Премиальный автосалон с 2010 года</p>
                </div>
                <ul class="nav-menu">
                    <li><a href="#home">Главная</a></li>
                    <li class="dropdown">
                        <a href="#catalog">Каталог <i class="fas fa-chevron-down"></i></a>
                        <ul class="dropdown-menu">
                            <li><a href="#porsche">Porsche</a></li>
                            <li><a href="#mercedes">Mercedes-Benz</a></li>
                            <li><a href="#bmw">BMW</a></li>
                            <li><a href="#audi">Audi</a></li>
                            <li><a href="#lexus">Lexus</a></li>
                        </ul>
                    </li>
                    <li><a href="#services">Услуги</a></li>
                    <li><a href="#about">О нас</a></li>
                    <li><a href="#contacts">Контакты</a></li>
                    <li><a href="#order-form" class="btn-contact">Заказать авто</a></li>
                </ul>
                <div class="mobile-menu-btn" id="mobileMenuBtn">
                    <i class="fas fa-bars"></i>
                </div>
            </div>
        </nav>
        
        <div class="mobile-menu" id="mobileMenu">
            <div class="mobile-menu-header">
                <h2>AutoElite</h2>
                <button class="close-menu" id="closeMenuBtn"><i class="fas fa-times"></i></button>
            </div>
            <ul class="mobile-nav">
                <li><a href="#home">Главная</a></li>
                <li><a href="#catalog">Каталог</a></li>
                <li><a href="#services">Услуги</a></li>
                <li><a href="#about">О нас</a></li>
                <li><a href="#contacts">Контакты</a></li>
                <li><a href="#order-form">Заказать авто</a></li>
            </ul>
        </div>
        
        <div class="hero">
            <div class="container">
                <h2>Эксклюзивные автомобили премиум-класса</h2>
                <p>Подберем идеальный автомобиль по вашим требованиям</p>
                <a href="#catalog" class="btn-hero">Смотреть каталог</a>
            </div>
        </div>
    </header>

    <main>
        <section class="popular-models" id="catalog">
            <div class="container">
                <h2 class="section-title">Популярные модели</h2>
                <p class="section-subtitle">Автомобили, которые выбирают наши клиенты</p>
                <div class="slider-container">
                    <div class="slider">
                        <div class="slide active">
                            <div class="slide-image">
                                <img src="https://i.pinimg.com/1200x/b9/ea/01/b9ea017e55f040aea05079c907258b35.jpg" alt="Porsche Panamera" class="car-image">
                                <div class="car-model-badge">Porsche</div>
                            </div>
                            <div class="slide-content">
                                <h3>Porsche Panamera</h3>
                                <p class="slide-description">Спортивная элегантность и мощность</p>
                                <p class="slide-price">от 9 500 000 ₽</p>
                                <a href="#order-form" class="btn-order">Заказать</a>
                            </div>
                        </div>
                        <div class="slide">
                            <div class="slide-image">
                                <img src="https://i.pinimg.com/1200x/5d/74/97/5d749788759bc112b30f99158c4a2b87.jpg" alt="Mercedes-Benz S-Class" class="car-image">
                                <div class="car-model-badge">Mercedes-Benz</div>
                            </div>
                            <div class="slide-content">
                                <h3>Mercedes-Benz S-Class</h3>
                                <p class="slide-description">Роскошь и инновации</p>
                                <p class="slide-price">от 12 000 000 ₽</p>
                                <a href="#order-form" class="btn-order">Заказать</a>
                            </div>
                        </div>
                        <div class="slide">
                            <div class="slide-image">
                                <img src="https://i.pinimg.com/1200x/20/cc/3b/20cc3b1b0ec4220d4d4e35e73de480c9.jpg" alt="BMW 7 Series" class="car-image">
                                <div class="car-model-badge">BMW</div>
                            </div>
                            <div class="slide-content">
                                <h3>BMW 7 Series</h3>
                                <p class="slide-description">Динамика и комфорт</p>
                                <p class="slide-price">от 8 900 000 ₽</p>
                                <a href="#order-form" class="btn-order">Заказать</a>
                            </div>
                        </div>
                    </div>
                    <button class="slider-btn prev-btn" id="prevBtn"><i class="fas fa-chevron-left"></i></button>
                    <button class="slider-btn next-btn" id="nextBtn"><i class="fas fa-chevron-right"></i></button>
                    <div class="slider-indicators">
                        <span class="indicator active" data-slide="0"></span>
                        <span class="indicator" data-slide="1"></span>
                        <span class="indicator" data-slide="2"></span>
                    </div>
                </div>
            </div>
        </section>

        <section class="services" id="services">
            <div class="container">
                <h2 class="section-title">Наши услуги</h2>
                <p class="section-subtitle">Полный комплекс услуг для вашего комфорта</p>
                <div class="services-grid">
                    <div class="service-card"><i class="fas fa-car service-icon"></i><h3>Продажа новых авто</h3><p>Широкий выбор новых автомобилей премиум-класса</p></div>
                    <div class="service-card"><i class="fas fa-credit-card service-icon"></i><h3>Кредитование</h3><p>Выгодные программы кредитования и лизинга</p></div>
                    <div class="service-card"><i class="fas fa-exchange-alt service-icon"></i><h3>Трейд-ин</h3><p>Выгодный обмен вашего автомобиля на новую модель</p></div>
                    <div class="service-card"><i class="fas fa-search service-icon"></i><h3>Поиск авто</h3><p>Поиск и доставка автомобилей по индивидуальным требованиям</p></div>
                    <div class="service-card"><i class="fas fa-tools service-icon"></i><h3>Сервисное обслуживание</h3><p>Полное ТО и ремонт автомобилей</p></div>
                    <div class="service-card"><i class="fas fa-spray-can service-icon"></i><h3>Детейлинг</h3><p>Премиум-уход за автомобилем</p></div>
                </div>
            </div>
        </section>

        <!-- Форма заказа автомобиля -->
        <section class="order-section" id="order-form">
            <div class="container">
                <div class="order-container">
                    <div class="order-header">
                        <h2>🚗 Оставить заявку на автомобиль</h2>
                        <p>Заполните форму, и мы свяжемся с вами в ближайшее время</p>
                    </div>
                    <div class="order-body">
                        <div id="ajaxMessages"></div>
                        
                        <?php foreach($messages as $m): ?>
                            <div class="success-msg"><?= $m ?></div>
                        <?php endforeach; ?>
                        
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <div class="auth-box" id="authSection">
                                <h3>🔐 Авторизация для редактирования заявки</h3>
                                <?php if (!empty($loginError)): ?>
                                    <div class="error-msg"><?= $loginError ?></div>
                                <?php endif; ?>
                                <form method="POST" id="loginForm">
                                    <div class="form-field">
                                        <label>Логин</label>
                                        <input type="text" name="login" id="loginInput">
                                    </div>
                                    <div class="form-field">
                                        <label>Пароль</label>
                                        <input type="password" name="password" id="passwordInput">
                                    </div>
                                    <button type="submit" name="login_submit" class="btn-send">Войти</button>
                                </form>
                            </div>
                            <hr>
                        <?php else: ?>
                            <div class="success-msg" id="userInfo">
                                ✅ Вы авторизованы как <?= htmlspecialchars($values['full_name']) ?>
                                <a href="?logout=1" class="logout-link">Выйти</a>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" id="orderForm">
                            <div class="form-field">
                                <label>ФИО *</label>
                                <input type="text" name="full_name" id="full_name" value="<?= htmlspecialchars($values['full_name'] ?? '') ?>">
                                <div class="error-msg" id="full_name_error"><?= $errors['full_name'] ?? '' ?></div>
                            </div>
                            
                            <div class="form-field">
                                <label>Телефон *</label>
                                <input type="tel" name="phone" id="phone" value="<?= htmlspecialchars($values['phone'] ?? '') ?>">
                                <div class="error-msg" id="phone_error"><?= $errors['phone'] ?? '' ?></div>
                            </div>
                            
                            <div class="form-field">
                                <label>E-mail *</label>
                                <input type="email" name="email" id="email" value="<?= htmlspecialchars($values['email'] ?? '') ?>">
                                <div class="error-msg" id="email_error"><?= $errors['email'] ?? '' ?></div>
                            </div>
                            
                            <div class="form-field">
                                <label>Дата рождения *</label>
                                <input type="date" name="birth_date" id="birth_date" value="<?= htmlspecialchars($values['birth_date'] ?? '') ?>">
                                <div class="error-msg" id="birth_date_error"><?= $errors['birth_date'] ?? '' ?></div>
                            </div>
                            
                            <div class="form-field">
                                <label>Пол *</label>
                                <div class="radio-group">
                                    <label><input type="radio" name="gender" value="male" <?= (($values['gender'] ?? '') == 'male') ? 'checked' : '' ?>> Мужской</label>
                                    <label><input type="radio" name="gender" value="female" <?= (($values['gender'] ?? '') == 'female') ? 'checked' : '' ?>> Женский</label>
                                    <label><input type="radio" name="gender" value="other" <?= (($values['gender'] ?? '') == 'other') ? 'checked' : '' ?>> Другой</label>
                                </div>
                                <div class="error-msg" id="gender_error"><?= $errors['gender'] ?? '' ?></div>
                            </div>
                            
                            <div class="form-field">
                                <label>Интересующие автомобили * (можно выбрать несколько)</label>
                                <select name="cars[]" id="cars" multiple>
                                    <?php foreach ($carsList as $car): ?>
                                        <option value="<?= $car['id'] ?>" <?= in_array($car['id'], $values['cars'] ?? []) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($car['name']) ?> - <?= htmlspecialchars($car['price']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="error-msg" id="cars_error"><?= $errors['cars'] ?? '' ?></div>
                            </div>
                            
                            <div class="form-field">
                                <label>Пожелания к заказу</label>
                                <textarea name="wish" id="wish" rows="4" placeholder="Цвет, комплектация, дополнительные опции..."><?= htmlspecialchars($values['wish'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="form-field">
                                <label class="checkbox-field">
                                    <input type="checkbox" name="contract" value="1" id="contract" <?= !empty($values['contract']) ? 'checked' : '' ?>>
                                    Я согласен с условиями обработки данных *
                                </label>
                                <div class="error-msg" id="contract_error"><?= $errors['contract'] ?? '' ?></div>
                            </div>
                            
                            <button type="submit" class="btn-send" id="formSubmitBtn">
                                <?= isset($_SESSION['user_id']) ? '✏️ Обновить заявку' : '🚗 Отправить заявку' ?>
                            </button>
                        </form>
                        
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <p style="margin-top: 20px; text-align: center; color: #666; font-size: 0.85em;">* После отправки заявки вы получите логин и пароль для редактирования</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <footer class="footer" id="contacts">
            <div class="container">
                <div class="footer-content">
                    <div class="footer-info">
                        <h3>AutoElite</h3>
                        <p>Премиальный автосалон с 2010 года</p>
                        <p>Москва, ул. Автозаводская, 25</p>
                        <p>+7 (495) 123-45-67</p>
                        <p>info@autoelite.ru</p>
                    </div>
                    <div class="footer-hours">
                        <h4>Часы работы</h4>
                        <p>Пн-Пт: 9:00 - 21:00</p>
                        <p>Сб: 10:00 - 20:00</p>
                        <p>Вс: 10:00 - 18:00</p>
                    </div>
                    <div class="footer-social">
                        <h4>Мы в соцсетях</h4>
                        <div class="social-icons">
                            <a href="#"><i class="fab fa-vk"></i></a>
                            <a href="#"><i class="fab fa-telegram"></i></a>
                            <a href="#"><i class="fab fa-instagram"></i></a>
                            <a href="#"><i class="fab fa-youtube"></i></a>
                        </div>
                    </div>
                </div>
                <div class="footer-bottom">
                    <p>&copy; 2024 AutoElite. Все права защищены.</p>
                </div>
            </div>
        </footer>
    </main>
    
    <!-- Кнопка для администратора -->
    <button class="admin-button" id="adminBtn">
        <i class="fas fa-shield-alt"></i> Администратору
    </button>

    <!-- Модальное окно для входа в админку -->
    <div class="modal-overlay hidden" id="adminModal">
        <div class="modal-window">
            <button class="modal-close-btn" id="modalClose"><i class="fas fa-times"></i></button>
            <h2 class="modal-title">🔐 Доступ администратора</h2>
            <form id="adminLoginForm">
                <div class="form-field">
                    <label>Логин администратора</label>
                    <input type="text" id="adminLogin" placeholder="Введите логин" required>
                </div>
                <div class="form-field">
                    <label>Пароль администратора</label>
                    <input type="password" id="adminPassword" placeholder="Введите пароль" required>
                </div>
                <button type="submit" class="btn-send">Войти в админ-панель</button>
                <div id="adminError" class="error-msg" style="margin-top: 15px; text-align: center;"></div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Слайдер
            const slider = document.querySelector('.slider');
            const slides = document.querySelectorAll('.slide');
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            const indicators = document.querySelectorAll('.indicator');
            let currentSlide = 0;
            const totalSlides = slides.length;
            
            function updateSlider() {
                if (slider) slider.style.transform = `translateX(-${currentSlide * 100}%)`;
                indicators.forEach((indicator, index) => {
                    if (index === currentSlide) indicator.classList.add('active');
                    else indicator.classList.remove('active');
                });
            }
            
            if (nextBtn) nextBtn.addEventListener('click', function() { currentSlide = (currentSlide + 1) % totalSlides; updateSlider(); });
            if (prevBtn) prevBtn.addEventListener('click', function() { currentSlide = (currentSlide - 1 + totalSlides) % totalSlides; updateSlider(); });
            indicators.forEach(indicator => {
                indicator.addEventListener('click', function() { currentSlide = parseInt(this.getAttribute('data-slide')); updateSlider(); });
            });
            
            let slideInterval = setInterval(() => { currentSlide = (currentSlide + 1) % totalSlides; updateSlider(); }, 5000);
            if (slider) {
                slider.addEventListener('mouseenter', () => clearInterval(slideInterval));
                slider.addEventListener('mouseleave', () => { slideInterval = setInterval(() => { currentSlide = (currentSlide + 1) % totalSlides; updateSlider(); }, 5000); });
            }
            
            // Плавная прокрутка
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    const href = this.getAttribute('href');
                    if (href === '#') return;
                    e.preventDefault();
                    const targetElement = document.querySelector(href);
                    if (targetElement) {
                        const headerHeight = document.querySelector('.navbar')?.offsetHeight || 80;
                        window.scrollTo({ top: targetElement.offsetTop - headerHeight - 20, behavior: 'smooth' });
                    }
                    const mobileMenu = document.getElementById('mobileMenu');
                    if (mobileMenu && mobileMenu.classList.contains('active')) mobileMenu.classList.remove('active');
                });
            });
            
            // Мобильное меню
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const mobileMenu = document.getElementById('mobileMenu');
            const closeMenuBtn = document.getElementById('closeMenuBtn');
            if (mobileMenuBtn) mobileMenuBtn.addEventListener('click', function() { mobileMenu.classList.add('active'); document.body.style.overflow = 'hidden'; });
            if (closeMenuBtn) closeMenuBtn.addEventListener('click', function() { mobileMenu.classList.remove('active'); document.body.style.overflow = ''; });
            
            // Фон навигации
            window.addEventListener('scroll', function() {
                const navbar = document.querySelector('.navbar');
                if (navbar) {
                    if (window.scrollY > 100) {
                        navbar.style.backgroundColor = 'rgba(13, 27, 42, 0.95)';
                        navbar.style.boxShadow = '0 5px 20px rgba(0, 0, 0, 0.1)';
                    } else {
                        navbar.style.backgroundColor = 'rgba(13, 27, 42, 0.9)';
                        navbar.style.boxShadow = 'none';
                    }
                }
            });
            
            updateSlider();
            
            // Админ модальное окно
            const adminBtn = document.getElementById('adminBtn');
            const adminModal = document.getElementById('adminModal');
            const modalClose = document.getElementById('modalClose');
            const adminLoginForm = document.getElementById('adminLoginForm');
            const adminError = document.getElementById('adminError');
            
            if (adminBtn) adminBtn.addEventListener('click', function() { adminModal.classList.remove('hidden'); document.body.style.overflow = 'hidden'; });
            if (modalClose) modalClose.addEventListener('click', function() { adminModal.classList.add('hidden'); document.body.style.overflow = ''; });
            adminModal?.addEventListener('click', function(e) { if (e.target === adminModal) { adminModal.classList.add('hidden'); document.body.style.overflow = ''; } });
            
            if (adminLoginForm) {
                adminLoginForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const login = document.getElementById('adminLogin').value;
                    const password = document.getElementById('adminPassword').value;
                    if (login === 'admin' && password === 'admin123') {
                        window.location.href = 'admin.php';
                    } else {
                        adminError.textContent = 'Неверный логин или пароль администратора';
                    }
                });
            }
            
            // AJAX отправка
            const orderForm = document.getElementById('orderForm');
            const formSubmitBtn = document.getElementById('formSubmitBtn');
            const ajaxMessages = document.getElementById('ajaxMessages');
            
            function clearErrors() {
                document.querySelectorAll('.error-msg').forEach(el => el.innerHTML = '');
                document.querySelectorAll('.form-field input, .form-field select, .form-field textarea').forEach(el => el.classList.remove('form-error'));
            }
            
            function showErrors(errors) {
                for (const [field, message] of Object.entries(errors)) {
                    const errorEl = document.getElementById(`${field}_error`);
                    if (errorEl) errorEl.innerHTML = message;
                    const inputEl = document.getElementById(field);
                    if (inputEl) inputEl.classList.add('form-error');
                    if (field === 'cars') document.getElementById('cars')?.classList.add('form-error');
                    if (field === 'contract') document.getElementById('contract')?.classList.add('form-error');
                }
            }
            
            function showMessage(message, isSuccess = true) {
                if (ajaxMessages) {
                    const msgDiv = document.createElement('div');
                    msgDiv.className = 'success-msg';
                    msgDiv.style.cssText = isSuccess ? '' : 'background:#f8d7da; color:#721c24;';
                    msgDiv.innerHTML = message;
                    ajaxMessages.appendChild(msgDiv);
                    setTimeout(() => msgDiv.remove(), 8000);
                }
            }
            
            if (orderForm) {
                orderForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    clearErrors();
                    if (ajaxMessages) ajaxMessages.innerHTML = '';
                    
                    const formData = new FormData(orderForm);
                    const data = {};
                    for (let [key, value] of formData.entries()) {
                        if (key === 'cars[]') { if (!data['cars']) data['cars'] = []; data['cars'].push(value); }
                        else if (key === 'contract') data['contract'] = true;
                        else data[key] = value;
                    }
                    if (!data['cars']) data['cars'] = [];
                    if (!data['contract']) data['contract'] = false;
                    
                    if (formSubmitBtn) { formSubmitBtn.disabled = true; formSubmitBtn.textContent = 'Отправка...'; }
                    
                    try {
                        const response = await fetch(window.location.href, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify(data)
                        });
                        const result = await response.json();
                        if (result.success) {
                            showMessage(result.messages.join('<br>'), true);
                            if (result.credentials) {
                                showMessage(`✅ Ваши данные для входа:<br><br>Логин: <b>${result.credentials.login}</b><br>Пароль: <b>${result.credentials.password}</b><br><br>⚠️ Сохраните их!`, true);
                            }
                            setTimeout(() => window.location.reload(), 3000);
                        } else if (result.errors) {
                            showErrors(result.errors);
                            showMessage('Пожалуйста, исправьте ошибки в форме.', false);
                        }
                    } catch (error) {
                        showMessage('Ошибка при отправке данных. Попробуйте еще раз.', false);
                    } finally {
                        if (formSubmitBtn) {
                            formSubmitBtn.disabled = false;
                            formSubmitBtn.textContent = '<?= isset($_SESSION['user_id']) ? '✏️ Обновить заявку' : '🚗 Отправить заявку' ?>';
                        }
                    }
                });
            }
        });
    </script>
    <script src="script.js"></script>
</body>
</html>
