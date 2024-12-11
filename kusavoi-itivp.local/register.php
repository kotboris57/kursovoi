<?php
try {
    include 'db.php';
} catch (PDOException $e) {
    $errors[] = "Ошибка подключения к базе данных. Пожалуйста, попробуйте позже.";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($errors)) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $email = trim($_POST['email']); 

    if (empty($username)) {
        $errors[] = "Имя пользователя не может быть пустым.";
    } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
        $errors[] = "Имя пользователя может содержать только буквы, цифры, точки, дефисы и нижние подчеркивания.";
    }

    if (empty($email)) {
        $errors[] = "Электронная почта не может быть пустой.";
    } else {
        if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+$/', $email)) {
            $errors[] = "Электронная почта должна содержать хотя бы один символ перед и после @.";
        }

        try {
            $sql_check_email = "SELECT COUNT(*) FROM users WHERE email = :email";
            $stmt_check_email = $pdo->prepare($sql_check_email);
            $stmt_check_email->execute(['email' => $email]);
            $email_exists = $stmt_check_email->fetchColumn();

            if ($email_exists) {
                $errors[] = "Электронная почта уже зарегистрирована.";
            }
        } catch (PDOException $e) {
            $errors[] = "Ошибка при проверке электронной почты: " . $e->getMessage();
        }
    }

    if (strlen($password) < 6) {
        $errors[] = "Пароль должен содержать не менее 6 символов.";
    }

    if ($password !== $password_confirm) {
        $errors[] = "Пароли не совпадают.";
    }

    try {
        $sql_check = "SELECT COUNT(*) FROM users WHERE username = :username";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute(['username' => $username]);
        $user_exists = $stmt_check->fetchColumn();

        if ($user_exists) {
            $errors[] = "Имя пользователя уже занято.";
        }
    } catch (PDOException $e) {
        $errors[] = "Ошибка при проверке имени пользователя: " . $e->getMessage();
    }


    if (empty($errors)) {
        $password_hashed = password_hash($password, PASSWORD_DEFAULT);
    
        $sql = "INSERT INTO users (username, password, email, role) VALUES (:username, :password, :email, :role)";
        
        try {
            $stmt = $pdo->prepare($sql);

            $stmt->execute(['username' => $username, 'password' => $password_hashed, 'email' => $email, 'role' => 'client']);
    
            header('Location: login.php');
            exit(); 
        } catch (PDOException $e) {
            $errors[] = "Ошибка базы данных: " . $e->getMessage();
        }
    }
}
?>


<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Регистрация</title>
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
        <h1>Регистрация</h1>

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
                <p><input type="text" name="username" placeholder="Имя пользователя" class="mar border" required></p>
                <p><input type="email" name="email" placeholder="Электронная почта" class="border" required></p>
                <p><input type="password" name="password" placeholder="Пароль" class="border" required></p>
                <p><input type="password" name="password_confirm" placeholder="Повторите пароль" class="border" required></p>
                <p><button type="submit" class="borderbutton">Зарегистрироваться</button></p>
            </form>
        </div>
        <p><a href="login.php" class="exitlog">Войти</a></p>
    </div>
</div>
</body>
</html>
