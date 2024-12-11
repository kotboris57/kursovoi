<?php
session_start();
$errors = [];

try {
    include 'db.php';
} catch (PDOException $e) {
    $errors[] = "Ошибка подключения к базе данных. Пожалуйста, попробуйте позже.";
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($errors)) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username)) {
        $errors[] = "Имя пользователя не может быть пустым.";
    }

    if (empty($password)) {
        $errors[] = "Пароль не может быть пустым.";
    }

    if (empty($errors)) {
        $sql = "SELECT * FROM users WHERE username = :username";
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['username' => $username]);

            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role']; 
                    $_SESSION['username'] = $user['username'];
                    switch ($user['role']) {
                        case 'employee':
                            header("Location: index.php");
                            break;
                        case 'administrator':
                            header("Location: admin.php");
                            break;
                        case 'client':
                            header("Location: client.php");
                            break;
                        default:
                            $errors[] = "Роль пользователя не определена.";
                    }
                    exit(); 
                } else {
                    $errors[] = "Неверный пароль.";
                }
            } else {
                $errors[] = "Пользователь не найден.";
            }
        } catch (PDOException $e) {
            $errors[] = "Ошибка базы данных. Пожалуйста, попробуйте позже.";
            error_log("Ошибка выполнения запроса: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Авторизация</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<style>
        .errors ul {
            list-style-type: none; 
            padding-left: 0; 
        }
    </style>
<body>
<div class="backgroun1">
    <div class="divinf">
        <h1>Авторизация</h1>

        <?php if (!empty($errors)): ?>
            <div class="errors">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="divavtor">
            <form method="post">
                <p><input type="text" name="username" placeholder="Имя пользователя" class="border" required></p>
                <p><input type="password" name="password" placeholder="Пароль" class="border" required></p>
                <p><button type="submit" class="borderbutton">Войти</button></p>
            </form>
        </div>
        <p><a href="register.php" class="exitlog">Зарегистрироваться</a></p>
    </div>
</div>
</body>
</html>
