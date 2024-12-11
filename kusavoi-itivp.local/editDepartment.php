<?php
session_start();
$username = $_SESSION['username'];

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'administrator') {
    header("Location: login.php");
    exit();
}

try {
    include 'db.php';
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных. Пожалуйста, попробуйте позже.");
}

$messages = [];
$errors = [];

// Получение ID отдела из запроса
$department_id = $_GET['id'] ?? null;

if (!$department_id) {
    die("Не указан ID отдела.");
}

// Получение данных отдела
try {
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = :id");
    $stmt->execute(['id' => $department_id]);
    $department = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$department) {
        die("Отдел не найден.");
    }
} catch (PDOException $e) {
    die("Ошибка при получении данных отдела: " . $e->getMessage());
}

// Обработка формы редактирования
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $city = trim($_POST['city']);
    $department_name = trim($_POST['department_name']);

    if (empty($city) || empty($department_name)) {
        $errors[] = "Все поля должны быть заполнены.";
    } else {
        try {
            $sql = "UPDATE departments SET city = :city, name = :name WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['city' => $city, 'name' => $department_name, 'id' => $department_id]);
            header('Location: admin.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = "Ошибка при обновлении отдела: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактирование отдела</title>
    <style>
         body{
            width: 90%;
            margin: 0 auto;
            height: 100vh; 
            font-family: "Fira Sans", sans-serif;
            color: #333;
        }
        .editing {
            text-align: center;
        }
        .post {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-evenly;
            height: 270px;
        }
        .post__input {
            width: 500px;
            text-align: center;
            height: 40px;
            border-radius: 15px;
            font-family: "Fira Sans", sans-serif;
            font-weight: 400;
            font-style: normal;
            font-size: 15px;
        }
        .label {
            font-weight: 400;
            font-style: normal;
            font-size: 21px;
        }
        .button {
            height: 50px;
            width: 150px;
            text-align: center;
            transition: 0.5s;
            background-size: 200% auto;
            color: white;
            box-shadow: 0 0 20px #eee;
            border-radius: 10px;
            background-image: linear-gradient(to right, #428ecf 0%, #0B63F6 51%, #428ecf 100%);
            font-family: "Fira Sans", sans-serif;
            font-weight: 400;
            font-style: normal;
        }
        .button:hover {
            background-position: right center;
        }
        .back {
            position: absolute;
            left: 20px;
            top: 15px;
            text-decoration: none;
            font-weight: 600;
            font-style: normal;
            color: white;
            font-size: 21px;
            padding: 10px 20px;
            background: #d3ebff;
            background-image: linear-gradient(to right, #428ecf 0%, #0B63F6 51%, #428ecf 100%);
            border-radius: 6px;
            transition: 0.5s;
            background-size: 200% auto;
            border: 1px solid black;
        }
        .back:hover {
            background-position: right center;
        }
    </style>
</head>
<body>
<a class="back" href="admin.php">Вернуться к списку</a>
    <h1 class="editing">Редактирование отдела</h1>

    <?php if (!empty($messages)): ?>
        <div class="success">
            <ul>
                <?php foreach ($messages as $message): ?>
                    <li><?= htmlspecialchars($message) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="errors">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form class="post" method="post">
        <label class="label" for="city">Город:</label>
        <input class="post__input" type="text" name="city" id="city" value="<?= htmlspecialchars($department['city']) ?>" required>
        
        <label class="label" for="department_name">Название отдела:</label>
        <input class="post__input" type="text" name="department_name" id="department_name" value="<?= htmlspecialchars($department['name']) ?>" required>
        
        <button class="button" type="submit">Сохранить изменения</button>
    </form>

</body>
</html>
