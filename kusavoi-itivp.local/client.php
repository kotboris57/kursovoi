<?php
session_start();
$username=$_SESSION['username'];
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header("Location: login.php");
    exit();
}

try {
    include 'db.php';
} catch (PDOException $e) {
    $errors[] = "Ошибка подключения к базе данных. Пожалуйста, попробуйте позже.";
}

$create_errors = [];
$create_success = "";
$search_errors = [];
$shipment_info = null; 

$departments = [];
 
function generateTrackingNumber($pdo) {
    do {
        $tracking_number = rand(100000, 999999); 
        $sql_check = "SELECT COUNT(*) FROM shipments WHERE tracking_number = :tracking_number";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute(['tracking_number' => $tracking_number]);
        $exists = $stmt_check->fetchColumn();
    } while ($exists); 
    return $tracking_number;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_shipment'])) {
    $sending_id = intval($_POST['sending']);
    $receiving_id = intval($_POST['receiving']);
    $user_id = $_SESSION['user_id']; // ID текущего пользователя
    $tracking_number = generateTrackingNumber($pdo);
    $package_type_id = intval($_POST['package_type_id']); // ID типа посылки
    $fast_delivery_id = intval($_POST['fast_delivery_id']); // ID типа доставки

    if (empty($sending_id) || empty($receiving_id)) {
        $create_errors[] = "Выберите оба отдела.";
    } else {
        if ($sending_id == $receiving_id) {
            $create_errors[] = "Отделы отправки и принятия не должны быть одинаковые.";
        } else {
            try {
                // Вставка нового заказа
                $sql = "INSERT INTO shipments (tracking_number, status, created_at, updated_at, 
                        sending_department_id, receiving_department_id, user_id, package_type_id, fast_delivery_id) 
                        VALUES (:tracking_number, 'в обработке', NOW(), NOW(), 
                        :sending_id, :receiving_id, :user_id, :package_type_id, :fast_delivery_id)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'tracking_number' => $tracking_number,
                    'sending_id' => $sending_id,
                    'receiving_id' => $receiving_id,
                    'user_id' => $user_id,
                    'package_type_id' => $package_type_id,
                    'fast_delivery_id' => $fast_delivery_id
                ]);

                // Обновление подсчета для типа посылки (с учетом пользователя)
                $update_sql = "INSERT INTO shipment_type_selections (shipment_type_id, user_id, selection_count)
                               VALUES (:package_type_id, :user_id, 1)
                               ON DUPLICATE KEY UPDATE selection_count = selection_count + 1";
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute([
                    'package_type_id' => $package_type_id,
                    'user_id' => $user_id
                ]);

                // Обновление подсчета для типа доставки (с учетом пользователя)
                $update_sql2 = "INSERT INTO shipment_delivery_selections (shipment_delivery_id, user_id, selection_count)
                                VALUES (:fast_delivery_id, :user_id, 1)
                                ON DUPLICATE KEY UPDATE selection_count = selection_count + 1";
                $update_stmt2 = $pdo->prepare($update_sql2);
                $update_stmt2->execute([
                    'fast_delivery_id' => $fast_delivery_id,
                    'user_id' => $user_id
                ]);

                $update_sql = "INSERT INTO department_user_stats (department_id, user_id, selection_count) 
                VALUES (:department_id, :user_id, 1)
                ON DUPLICATE KEY UPDATE selection_count = selection_count + 1";
 
                 $stmt = $pdo->prepare($update_sql);
                 $stmt->execute(['department_id' => $sending_id, 'user_id' => $user_id]);
                 $stmt->execute(['department_id' => $receiving_id, 'user_id' => $user_id]);
                $create_success = "Заказ успешно создан. Номер отслеживания: $tracking_number";
            } catch (PDOException $e) {
                $create_errors[] = "Ошибка базы данных: " . $e->getMessage();
            }
        }
    }
}




if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search_shipment'])) {
    $tracking_number = trim($_POST['tracking_number']);

    if (empty($tracking_number)) {
        $search_errors[] = "Номер отслеживания не может быть пустым.";
    } else {
        $sql = "SELECT 
                s.tracking_number, 
                s.status, 
                s.created_at, 
                s.updated_at, 
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
            WHERE s.tracking_number = :tracking_number";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['tracking_number' => $tracking_number]);
            $shipment_info = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$shipment_info) {
                $search_errors[] = "Посылка с данным номером отслеживания не найдена.";
            }
        } catch (PDOException $e) {
            $search_errors[] = "Ошибка базы данных: " . $e->getMessage();
        }
    }
}

$user_orders = [];
try {
   $sql = "SELECT s.*, 
       sd.city AS sending_city, sd.name AS sending_name, 
       rd.city AS receiving_city, rd.name AS receiving_name,
       st.package_type AS package_type_name, 
       sd2.fast_delivery AS fast_delivery,
       COALESCE(sending_stats.selection_count, 0) AS sending_count,
       COALESCE(receiving_stats.selection_count, 0) AS receiving_count,
       COALESCE(package_type_stats.selection_count, 0) AS package_type_count,
       COALESCE(fast_delivery_stats.selection_count, 0) AS fast_delivery_count,

       -- Индекс популярности
       (COALESCE(sending_stats.selection_count, 0) + 
        COALESCE(receiving_stats.selection_count, 0)) * COALESCE(sw2.weight, 0) +
       COALESCE(package_type_stats.selection_count, 0) * COALESCE(sw4.weight, 0) +
       COALESCE(fast_delivery_stats.selection_count, 0) * COALESCE(sw5.weight, 0) AS popularity_score

        FROM shipments s
        JOIN departments sd ON s.sending_department_id = sd.id
        JOIN departments rd ON s.receiving_department_id = rd.id
        LEFT JOIN shipment_types st ON s.package_type_id = st.id
        LEFT JOIN shipment_delivery sd2 ON s.fast_delivery_id = sd2.id

        -- Подключение весов
        LEFT JOIN sorting_weights sw2 ON sw2.parameter = 'department_id'
        LEFT JOIN sorting_weights sw4 ON sw4.parameter = 'package_type_id'
        LEFT JOIN sorting_weights sw5 ON sw5.parameter = 'fast_delivery_id'

        -- Подключение статистики выбора
        LEFT JOIN department_user_stats sending_stats ON sending_stats.department_id = sd.id AND sending_stats.user_id = :user_id
        LEFT JOIN department_user_stats receiving_stats ON receiving_stats.department_id = rd.id AND receiving_stats.user_id = :user_id
        LEFT JOIN shipment_type_selections package_type_stats ON package_type_stats.shipment_type_id = st.id AND package_type_stats.user_id = :user_id
        LEFT JOIN shipment_delivery_selections fast_delivery_stats ON fast_delivery_stats.shipment_delivery_id = sd2.id AND fast_delivery_stats.user_id = :user_id

        WHERE s.user_id = :user_id
        ORDER BY popularity_score DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $user_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $create_errors[] = "Ошибка получения истории заказов: " . $e->getMessage();
}


try {
    $sql = "SELECT d.id, d.city, d.name, COALESCE(s.selection_count, 0) AS selection_count
        FROM departments d
            LEFT JOIN department_user_stats s 
        ON d.id = s.department_id AND s.user_id = :user_id
        ORDER BY selection_count DESC, d.city, d.name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $user_departments_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $create_errors[] = "Ошибка получения статистики: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Создание заказа</title>
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

        .create_success{
            list-style: none;
            text-align: center;
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

        .infa{
            display: flex;
            flex-direction: column;
            align-items: center;
            list-style: none;
        }

        .infa ul{
            list-style: none;
        }
        .errors{
            display: flex;
            flex-direction: column;
            align-items: center;
            list-style: none;
        }
        .table__result{
            display: flex;
            flex-direction: column;
            align-items: center;
        }
    </style>
</head>
<body>
<div >
    <div class="top">
        <p>Логин: <?= htmlspecialchars($username ) ?></p>
        <h1 class="treking treking2">Создание заказа</h1>
        <a href="logout.php" class="exit">ВЫХОД</a>
    </div>
        <?php if (!empty($create_errors)): ?>
            <div class="errors" style="color: red; text-align: center;">
                    <?php foreach ($create_errors as $error): ?>
                        <p><?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($create_success): ?>
            <div class="success">
                <p class="create_success"><?= htmlspecialchars($create_success) ?></p>
            </div>
        <?php endif; ?>

        <form method="post" class="post">
            <p>
            <select class="post__input" name="sending" required>
                <option value="" disabled selected>Выберите город и отдел отправки</option>
                <?php foreach ($user_departments_stats as $department): ?>
                <option value="<?= htmlspecialchars($department['id']) ?>">
                <?= htmlspecialchars($department['city'] . ", " . $department['name']) ?>
                </option>
            <?php endforeach; ?>
            </select>
            </p>
            <p>
                <select class="post__input" name="receiving" required>
                    <option value="" disabled selected>Выберите город и отдел получения</option>
                    <?php foreach ($user_departments_stats as $department): ?>
                    <option value="<?= htmlspecialchars($department['id']) ?>">
                    <?= htmlspecialchars($department['city'] . ", " . $department['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <select class="post__input" name="package_type_id" id="package_type_id" required>
                <option value="" disabled selected>Выберите тип посылки</option>
                    <?php
                    $sql = "SELECT st.id, st.package_type, IFNULL(sts.selection_count, 0) AS selection_count
                            FROM shipment_types st
                            LEFT JOIN shipment_type_selections sts ON st.id = sts.shipment_type_id AND sts.user_id = :user_id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['user_id' => $user_id]);
                    $package_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($package_types as $type):
                    ?>
                        <option value="<?= htmlspecialchars($type['id']) ?>">
                            <?= htmlspecialchars($type['package_type']) ?> 
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p>
                <select class="post__input" name="fast_delivery_id" id="fast_delivery_id" required>
                <option value="" disabled selected>Нужна ли быстрая доставка?</option>
                    <?php
                    $sql = "SELECT sd.id, sd.fast_delivery, IFNULL(sds.selection_count, 0) AS selection_count
                            FROM shipment_delivery sd
                            LEFT JOIN shipment_delivery_selections sds ON sd.id = sds.shipment_delivery_id AND sds.user_id = :user_id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['user_id' => $user_id]);
                    $delivery_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($delivery_types as $type):
                    ?>
                        <option value="<?= htmlspecialchars($type['id']) ?>">
                        <?= htmlspecialchars($type['fast_delivery'] == 1 ? 'Да' : 'Нет') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p><button class="button" type="submit" name="create_shipment">Создать заказ</button></p>
        </form>
        
        <h2 class="treking treking2">Поиск заказа по номеру отслеживания</h2>
        <?php if (!empty($search_errors)): ?>
            <div class="errors">
                <ul>
                    <?php foreach ($search_errors as $error): ?>
                        <li class="create_success"><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form method="post" class="get">
            <p><input class="post__input" type="text" name="tracking_number" placeholder="Введите номер отслеживания" class="border" required></p>
            <p><button class="button" type="submit" name="search_shipment" class="borderbutton">Найти заказ</button></p>
        </form>

        <div class="infa">
            <?php if ($shipment_info): ?>
                <h2 class="treking treking2">Информация о посылке</h2>
                <ul>
                    <li>Номер отслеживания: <?= htmlspecialchars($shipment_info['tracking_number']) ?></li>
                    <li>Статус: <?= htmlspecialchars($shipment_info['status']) ?></li>
                    <li>Тип посылки: <?= htmlspecialchars($shipment_info['package_type_name']) ?></li>
                    <li>Быстрая доставка: <?= htmlspecialchars($shipment_info['fast_delivery'] == 1 ? 'Да' : 'Нет') ?></td></li>
                    <li>Дата создания: <?= htmlspecialchars($shipment_info['created_at']) ?></li>
                    <li>Дата обновления: <?= htmlspecialchars($shipment_info['updated_at']) ?></li>
                    <li>Адрес отправки: <?= htmlspecialchars($shipment_info['sending_city'] . ", " . $shipment_info['sending_name']) ?></li>
                    <li>Адрес получения: <?= htmlspecialchars($shipment_info['receiving_city'] . ", " . $shipment_info['receiving_name']) ?></li>

                </ul>
            <?php endif; ?>
        </div>
        <div class="table__result">
            <h2 class="list">История ваших заказов</h2>
            <?php if (!empty($user_orders)): ?>
                <table class="table">
                    <tr class="tr">
                        <th class="td">Номер отслеживания</th>
                        <th class="td">Статус</th>
                        <th class="td">Тип посылки</th>
                        <th class="td">Быстрая доставка</th>
                        <th class="td">Дата создания</th>
                        <th class="td">Дата обновления</th>
                        <th class="td">Адрес отправки</th>
                        <th class="td">Адрес получения</th>
                    </tr>
                    <?php foreach ($user_orders as $order): ?>
                        <tr class="tr">
                            <td class="td"><?= htmlspecialchars($order['tracking_number']) ?></td>
                            <td class="td"><?= htmlspecialchars($order['status']) ?></td>
                            <td class="td"><?= htmlspecialchars($order['package_type_name']) ?></td>
                            <td class="td"><?= htmlspecialchars($order['fast_delivery'] == 1 ? 'Да' : 'Нет') ?></td>

                            <td class="td"><?= htmlspecialchars($order['created_at']) ?></td>
                            <td class="td"><?= htmlspecialchars($order['updated_at']) ?></td>
                            <td class="td"><?= htmlspecialchars($order['sending_city'] . ", " . $order['sending_name']) ?></td>
                            <td class="td"><?= htmlspecialchars($order['receiving_city'] . ", " . $order['receiving_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>

            <?php else: ?>
                <tr class="not__found">
                    <td class="td__not__found" colspan="4">Посылки не найдены</td>
                </tr>
            <?php endif; ?>
        </div>
</div>
</body>
</html>