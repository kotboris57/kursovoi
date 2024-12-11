<?php

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header('Location: login.php');
    exit();
}
$search="";
$username="";
$departmentInfo="";
$errors = []; 
try {
    include 'db.php'; 
} catch (PDOException $e) {
    $errors[] = "Ошибка подключения к базе данных. Пожалуйста, попробуйте позже.";
    $pdo = null; 
}
if ($pdo) { 
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'];

    $sql = "
        SELECT 
            u.username, 
            d.name AS department_name, 
            d.city,
            u.department_id
        FROM users u
        JOIN departments d ON u.department_id = d.id
        WHERE u.id = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        $errors[] = "Не удалось загрузить информацию о пользователе.";
    } else {
        $department_id = $userData['department_id'];
        $departmentInfo = htmlspecialchars($userData['department_name'] . ' (' . $userData['city'] . ')');
    }

    // Обработка поиска и удаления
    if (isset($_GET['delete'])) {
        $id = $_GET['delete'];
        $stmt = $pdo->prepare("DELETE FROM shipments WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success_message'] = "Удаление прошло успешно!";
    }

    $search = '';
    if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
        $search = trim($_GET['search']);
        $sql = "
            SELECT 
                s.*,
                sd.city AS sending_city, 
                sd.name AS sending_name, 
                rd.city AS receiving_city, 
                rd.name AS receiving_name,
                st.package_type AS package_type_name, 
                sd2.fast_delivery AS fast_delivery
            FROM shipments s
            JOIN departments sd ON s.sending_department_id = sd.id
            JOIN departments rd ON s.receiving_department_id = rd.id
            LEFT JOIN shipment_types st ON s.package_type_id = st.id
            LEFT JOIN shipment_delivery sd2 ON s.fast_delivery_id = sd2.id
            WHERE (s.sending_department_id = :department_id OR s.receiving_department_id = :department_id)
                AND s.tracking_number LIKE :search
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'department_id' => $department_id,
            'search' => '%' . $search . '%'
        ]);
    } else {
        $sql = "
            SELECT 
                s.*,
                sd.city AS sending_city, 
                sd.name AS sending_name, 
                rd.city AS receiving_city, 
                rd.name AS receiving_name,
                st.package_type AS package_type_name, 
                sd2.fast_delivery AS fast_delivery
            FROM shipments s
            JOIN departments sd ON s.sending_department_id = sd.id
            JOIN departments rd ON s.receiving_department_id = rd.id
            LEFT JOIN shipment_types st ON s.package_type_id = st.id
            LEFT JOIN shipment_delivery sd2 ON s.fast_delivery_id = sd2.id
            WHERE s.sending_department_id = :department_id OR s.receiving_department_id = :department_id
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['department_id' => $department_id]);
    }
    $shipments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $shipments = [];
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Трекинг посылок</title>
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
            margin-left: 0px;
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
        .th2{
            padding: 10px 70px;
        }
        .td {
            background: #D8E6F3;
        }
    
        .th:first-child, td:first-child {
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
        }

        .delete:hover{
            background-color: #c2d6e9;
            border-radius: 6px;
        }
        .exit {
            margin-left: auto;
            padding: 10px 20px;
            background-color: #007BFF;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
            box-shadow: 0 0 20px #eee;

            background-image: linear-gradient(to right, #428ecf 0%, #0B63F6 51%, #428ecf 100%);

            font-family: "Fira Sans", sans-serif;
            font-weight: 400;
            font-style: normal;
        }

        .exit:hover {
            background-color: #0056b3;
        }
        .top {
            display: flex;
            justify-content: space-between; 
            align-items: center;
            width: 100%;
        }
        .success {
            padding: 10px;
            width: 70%;
            margin: 0 auto;
        }

    </style>
</head>
<body>
    <div class="top">
        <p>Логин: <?= $username ?><br/>
        Отдел: <?= $departmentInfo ?></p>
        <h1 class="treking treking2">Отслеживание посылок</h1>
        <a href="logout.php" class="exit">ВЫХОД</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="error" style="color: red; text-align: center;">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="success" style="color: green; text-align: center;">
            <p><?= htmlspecialchars($_SESSION['success_message']) ?></p>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <form method="GET" class="get">
        <input class="post__input" type="text" name="search" placeholder="Поиск по номеру отслеживания" value="<?= htmlspecialchars($search) ?>" maxlength="9">
        <button class="button" type="submit">Найти</button>
    </form>

    <div class="table__result">
        <h2 class="list">Список посылок</h2>
        <table class="table">
            <tr class="tr">
            <th class="th">Номер отслеживания</th>
            <th class="th">Статус</th>
            <th class="th">Тип посылки</th>
            <th class="th">Быстрая доставка</th>
            <th class="th">Дата создания</th>
            <th class="th">Дата обновления</th>
            <th class="th">Адрес отправки</th>
            <th class="th">Адрес получения</th>
            <th class="th th2">Действия</th>
            </tr>
            <?php if (count($shipments) > 0): ?>
                <?php foreach ($shipments as $shipment): ?>
                    <tr class="tr">
                        <td class="td"><?= htmlspecialchars($shipment['tracking_number']) ?></td>
                        <td class="td"><?= htmlspecialchars($shipment['status']) ?></td>
                        <td class="td"><?= htmlspecialchars($shipment['package_type_name']) ?></td>
                        <td class="td"><?= htmlspecialchars($shipment['fast_delivery'] == 1 ? 'Да' : 'Нет') ?></td>

                        <td class="td"><?= htmlspecialchars($shipment['created_at']) ?></td>
                        <td class="td"><?= htmlspecialchars($shipment['updated_at']) ?></td>
                        <td class="td"><?= htmlspecialchars($shipment['sending_city'] . ", " . $shipment['sending_name']) ?></td>
                        <td class="td"><?= htmlspecialchars($shipment['receiving_city'] . ", " . $shipment['receiving_name']) ?></td>
                        <td class="td">
                            <a class="delete" href="edit.php?id=<?= $shipment['id'] ?>">Редактировать</a>
                            <a class="delete" href="javascript:void(0);" onclick="confirmDelete(<?= $shipment['id'] ?>)">Удалить</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php else: ?>
                    <tr class="not__found">
                        <td class="td__not__found" colspan="9">Посылки не найдены</td>
                    </tr>
                <?php endif; ?>
        </table>
        <div style="text-align: center; margin-top: 20px;">
            <button class="button" onclick="location.href='statistics.php';">Перейти к статистике</button>
        </div>
    </div>
    <script>

        function confirmDelete(id) {
            if (confirm('Вы уверены, что хотите удалить эту посылку?')) {
                window.location.href = '?delete=' + id;
            }
        }
    </script>
</body>
</html>

