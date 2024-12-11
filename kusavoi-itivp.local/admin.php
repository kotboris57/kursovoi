<?php
session_start();
$username=$_SESSION['username'];
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
 

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_changes'])) {
    $user_id = $_POST['user_id'];
    $new_role = $_POST["role_$user_id"];
    $new_department_id = $_POST["department_id_$user_id"] ?: null;

    if (!in_array($new_role, ['administrator', 'employee', 'client'])) {
        $errors[] = "Некорректная роль.";
    } else {
        if (!is_null($new_department_id) && $new_role !== 'employee') {
            $errors[] = "Отдел можно назначить только пользователю с ролью 'employee'.";
        } else {
            // Выполняем обновление в базе данных
            $sql = "UPDATE users SET role = :role, department_id = :department_id WHERE id = :id";
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'role' => $new_role,
                    'department_id' => $new_role === 'employee' ? $new_department_id : null,
                    'id' => $user_id
                ]);
                $messages[] = "Изменения успешно сохранены для пользователя с ID $user_id.";
            } catch (PDOException $e) {
                $errors[] = "Ошибка при сохранении изменений: " . $e->getMessage();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_department'])) {
    $department_id = $_POST['department_id'];

    
        $sql = "DELETE FROM departments WHERE id = :id";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id' => $department_id]);
            $messages[] = "Пользователь успешно удален.";
        } catch (PDOException $e) {
            $errors[] = "Ошибка при удалении пользователя: " . $e->getMessage();
        }
    
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];

    if ($user_id == $_SESSION['user_id']) {
        $errors[] = "Вы не можете удалить самого себя.";
    } else {
        $sql = "DELETE FROM users WHERE id = :id";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id' => $user_id]);
            $messages[] = "Пользователь успешно удален.";
        } catch (PDOException $e) {
            $errors[] = "Ошибка при удалении пользователя: " . $e->getMessage();
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_department'])) {
    $city = trim($_POST['city']);
    $department_name = trim($_POST['department_name']);

    if (empty($city) || empty($department_name)) {
        $errors[] = "Все поля должны быть заполнены.";
    } else {
        $sql = "INSERT INTO departments (city, name) VALUES (:city, :name)";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['city' => $city, 'name' => $department_name]);
            $messages[] = "Отдел успешно добавлен.";
        } catch (PDOException $e) {
            $errors[] = "Ошибка при добавлении отдела: " . $e->getMessage();
        }
    }
}

$sql = "SELECT * FROM departments ORDER BY city, name";
try {
    $stmt = $pdo->query($sql);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Ошибка при получении списка отделов: " . $e->getMessage());
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_weights'])) {
    $weights = [
        'department_id' => floatval($_POST['department_weight']),
        'package_type_id' => floatval($_POST['package_weight']),
        'fast_delivery_id' => floatval($_POST['delivery_weight'])
    ];


    if (empty($errors)) {
        try {
            // Обновляем каждый коэффициент
            foreach ($weights as $param => $weight) {
                $sql = "UPDATE sorting_weights SET weight = :weight WHERE parameter = :parameter";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['weight' => $weight, 'parameter' => $param]);
            }
            $messages[] = "Весовые коэффициенты успешно обновлены.";
        } catch (PDOException $e) {
            $errors[] = "Ошибка при сохранении коэффициентов: " . $e->getMessage();
        }
    }
}

$weights_map = []; // Массив для хранения весов

// Выполняем запрос к базе данных для получения весов
$sql = "SELECT * FROM sorting_weights";
try {
    // Выполнение запроса с использованием PDO
    $stmt = $pdo->query($sql);
    $weights_map = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['parameter'] == 'fast_delivery_id') {
            $weights_map['fast_delivery_id'] = $row['weight'];
        }
        if ($row['parameter'] == 'package_type_id') {
            $weights_map['package_type_id'] = $row['weight'];
        }
        if ($row['parameter'] == 'department_id') {
            $weights_map['department_id'] = $row['weight'];
        }
       // Проверка существования ключей в массиве и передача значений в htmlspecialchars

$weightReceiving = isset($array['department_weight']) ? htmlspecialchars($array['department_weight']) : '';
$weightType = isset($array['weight_type']) ? htmlspecialchars($array['weight_type']) : '';
$fastDeliveryWeight = isset($array['fast_delivery_weight']) ? htmlspecialchars($array['fast_delivery_weight']) : '';

    }
} catch (PDOException $e) {
    die("Ошибка при получении данных о весах: " . $e->getMessage());
}


$sql = "
    SELECT u.id, u.username, u.role, d.city, d.name, d.id as department_id 
    FROM users u 
    LEFT JOIN departments d ON u.department_id = d.id
";
try {
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Ошибка при получении списка пользователей: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление пользователями и отделами</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Fira+Sans:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap');
        body{
            height: 100vh; 
            font-family: "Fira Sans", sans-serif;
            color: #333;
        }

        .treking {
            flex: 1;
            text-align: center; 
            margin-left: 80px;
        }

        .treking2 {
            margin-left: 0;
        }

        .post{
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-evenly;
            width: 90%;
            height: 240px;
            margin: 0 auto;
        }
        p{
            margin-top: 0;
            margin-bottom: 0;
        }
        
        .post__input{
            width: 500px;
            text-align: center;
            height: 40px;
            border-radius: 15px;
            font-family: "Fira Sans", sans-serif;
            font-weight: 400;
            font-style: normal;
            font-size: 15px;
        }
        
        .button{
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


        .get{
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-evenly;
            width: 90%;
            height: 150px;
            margin: 0 auto;
        }

        .table {
            width: 70%;
            margin: 0 auto;
            font-size: 14px;
            border-collapse: collapse;
            text-align: center;

            font-family: "Fira Sans", sans-serif;
            /* font-weight: 400; */
            font-style: normal;
        }
    
        .th, .td:first-child {
            background: #AFCDE7;
            color: white;
            padding: 10px 20px;
        }

        .th, .td {
            font-family: "Fira Sans", sans-serif;
            font-weight: 400;
            font-style: normal;
            border-style: solid;
            border-width: 0 1px 1px 0;
            border-color: white;
        }
    
        .td {
            background: #D8E6F3;
        }
    
        .th:first-child, td:first-child {
            /* text-align: left; */
            width: 40px;
        }

        .list{
            text-align: center;
        }

        .not__found{
            padding-top: 20px;
            font-family: "Fira Sans", sans-serif;
            font-weight: 400;
            font-style: normal;
            font-size: 20px;
        }

        .td__not__found{
            padding-top: 20px;
            text-align: center;
        }

        .delete{
            text-decoration: none;
            padding: 5px;
            color: black;
            font-family: "Fira Sans", sans-serif;
            font-weight: 400;
            font-style: normal;
            font-size: 15px;
            background: #D8E6F3;
            border: none;
        }

        .delete:hover{
            background-color: #c2d6e9;
            border-radius: 6px;
            cursor: pointer;
        }
        .exit {
            margin-left: auto; /* Сдвигает ссылку к правому краю */
            padding: 10px 20px; /* Добавляет отступы для кнопки */
            background-color: #007BFF; /* Цвет фона кнопки */
            color: white; /* Цвет текста */
            text-decoration: none; /* Убирает подчеркивание ссылки */
            border-radius: 5px; /* Скругляет углы кнопки */
            transition: background-color 0.3s; /* Плавный переход цвета фона */
            box-shadow: 0 0 20px #eee;

            background-image: linear-gradient(to right, #428ecf 0%, #0B63F6 51%, #428ecf 100%);

            font-family: "Fira Sans", sans-serif;
            font-weight: 400;
            font-style: normal;
        }

        .exit:hover {
            background-color: #0056b3; /* Цвет фона при наведении */
        }
        .top {
            display: flex;
            justify-content: space-between; /* Распределяет элементы по краям */
            align-items: center; /* Центрирует элементы по вертикали */
            width: 100%; /* Занимает всю ширину родителя */
        }

        .select{
            border: none;
            background: #D8E6F3;
            text-align: center;
            font-family: "Fira Sans", sans-serif;
            font-weight: 400;
            font-style: normal;
            font-size: 15px;
        }

        .select:hover{
            cursor: pointer;
        }
        .form-weights {
            width: 50%;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
            background-color: #f9f9f9;
            box-shadow: 0px 2px 4px rgba(0, 0, 0, 0.1);
            font-family: "Fira Sans", sans-serif;
        }

        .form-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .form-row label {
            font-size: 16px;
            font-weight: 500;
        }

        .form-row input[type="number"] {
            width: 150px;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .success {
            color: #28a745;
            font-weight: bold;
            text-align: center;
            margin-bottom: 10px;
        }
        .error{
            display: flex;
            flex-direction: column;
            align-items: center;
            list-style: none;
            margin-left: -60px;
        }
        .table__result{
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
    </style>
</head>
<body>
    <div class="top">
    <p>Логин: <?= htmlspecialchars($username ) ?></p>
        <h1 class="treking treking2">Управление пользователями и отделами</h1>
        <a href="logout.php" class="exit">ВЫХОД</a>
    </div>
    <?php if (!empty($messages)): ?>
            <div class="success" style="color: green; text-align: center;">
                <ul>
                    <?php foreach ($messages as $message): ?>
                        <li class="error"><?= htmlspecialchars($message) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="errors" style="color: red; text-align: center;">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li class="error"><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    <div>
    <form method="post" class="form-weights">
    <div class="form-row">
        <label for="department_weight">Вес адреса:</label>
        <input type="number" name="department_weight" value="<?= htmlspecialchars($weights_map['department_id']) ?>" min="0.1" max="2" step="0.1">
    </div>

    <div class="form-row">
        <label for="package_weight">Вес типа посылки:</label>
        <input type="number" name="package_weight" value="<?= htmlspecialchars($weights_map['package_type_id']) ?>" min="0.1" max="2" step="0.1">
    </div>

    <div class="form-row">
        <label for="delivery_weight">Вес быстрой доставки:</label>
        <input type="number" name="delivery_weight" value="<?= htmlspecialchars($weights_map['fast_delivery_id']) ?>" min="0.1" max="2" step="0.1">
    </div>

    <button class="button" type="submit" name="save_weights">Обновить веса</button>
</form>


    <h2 class ="list">Добавить отдел</h2>
    <form method="post" class="post">
        <input class="post__input" type="text"placeholder="Город" name="city" id="city" required>
        <input class="post__input" type="text" placeholder="Название отдела" name="department_name" id="department_name" required>
        <button class="button" type="submit" name="add_department">Добавить отдел</button>
    </form>
</div>
<div>
<h2 class="list">Список отделов</h2>
<table class="table" border="1">
    <thead>
        <tr class="tr">
            <th class="th">Город</th>
            <th class="th">Название отдела</th>
            <th class="th">Действия</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($departments as $department): ?>
            <tr >
                <td class="td"><?= htmlspecialchars($department['city']) ?></td>
                <td class="td"><?= htmlspecialchars($department['name']) ?></td>
                <td class="td">
                <a class="delete" href="editDepartment.php?id=<?= $department['id'] ?>">Редактировать</a>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="department_id" value="<?= $department['id'] ?>">
                        <button class="delete" type="submit" name="delete_department" onclick="return confirm('Вы уверены, что хотите удалить этот отдел?')">Удалить</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
    </div>
    <div class="table_result">
        <h2 class="list">Список пользователей</h2>
        <table class="table" border="1">
            <thead>
                <tr class="tr">
                    <th class="th">Имя пользователя</th>
                    <th class="th">Роль</th>
                    <th class="th">Отдел</th>
                    <th class="th">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr class="tr">
                        <td class="td"><?= htmlspecialchars($user['username']) ?></td>
                        <td class="td">
                <!-- Выпадающий список для роли -->
                            <select class="select" name="role_<?= $user['id'] ?>" form="form_<?= $user['id'] ?>">
                                <option value="administrator" <?= $user['role'] === 'administrator' ? 'selected' : '' ?>>Администратор</option>
                                <option value="employee" <?= $user['role'] === 'employee' ? 'selected' : '' ?>>Работник</option>
                                <option value="client" <?= $user['role'] === 'client' ? 'selected' : '' ?>>Клиент</option>
                            </select>
                        </td>
                        <td class="td">
                <!-- Выпадающий список для отдела -->
                            <select class="select" name="department_id_<?= $user['id'] ?>" form="form_<?= $user['id'] ?>">
                                <option value="" <?= is_null($user['department_id']) ? 'selected' : '' ?>>Не назначен</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?= $department['id'] ?>" <?= $user['department_id'] == $department['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($department['city'] . " - " . $department['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="td">
                <!-- Кнопка "Сохранить" для каждого пользователя -->
                            <form id="form_<?= $user['id'] ?>" method="post">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <button class="delete" type="submit" name="save_changes">Сохранить</button>
                                <button class="delete" type="submit" name="delete_user" onclick="return confirm('Вы уверены, что хотите удалить этого пользователя?')">Удалить</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
