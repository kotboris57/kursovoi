<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header('Location: login.php');
    exit();
}

try {
    include 'db.php';
} catch (PDOException $e) {
    header('Location: index.php');
    exit();
}

$id = $_GET['id'] ?? null;

$department = [];
try {
    $sql = "SELECT id, city, name FROM departments";
    $stmt = $pdo->query($sql);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Ошибка загрузки отделов: " . $e->getMessage();
}

if ($id) {
    $stmt = $pdo->prepare("
        SELECT sending_department_id, receiving_department_id, status, package_type_id, fast_delivery_id 
        FROM shipments 
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $shipment = $stmt->fetch(PDO::FETCH_ASSOC);

    $is_editable = $shipment['status'] === 'в обработке';
}

// Получение списка типов доставки и быстрой доставки
try {
    $stmt = $pdo->query("SELECT id, package_type FROM shipment_types");
    $package_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT id, fast_delivery FROM shipment_delivery");
    $fast_delivery_options = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Ошибка загрузки типов доставки: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $sending_department_id = $_POST['sending'];
    $receiving_department_id = $_POST['receiving'];
    $status = $_POST['status'];
    $package_type_id = $_POST['package_type'];
    $fast_delivery_id = $_POST['fast_delivery'];

    if ($is_editable) {
        $sending_department_id = $_POST['sending'];
        $receiving_department_id = $_POST['receiving'];
    } else {
        $sending_department_id = $shipment['sending_department_id'];
        $receiving_department_id = $shipment['receiving_department_id'];
    }

    if ($sending_department_id == $receiving_department_id) {
        $errors[] = "Место отправки и место получения должны быть разные.";
    } else {
        $stmt_update = $pdo->prepare("
            UPDATE shipments 
            SET status = ?, sending_department_id = ?, receiving_department_id = ?, 
                package_type_id = ?, fast_delivery_id = ?
            WHERE id = ?
        ");
        $stmt_update->execute([
            $status, 
            $sending_department_id, 
            $receiving_department_id, 
            $package_type_id, 
            $fast_delivery_id, 
            $id
        ]);
        $_SESSION['success_message'] = "Редактирование прошло успешно!";
        header('Location: index.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактирование посылки</title>
    <style>
        body{
            width: 90%;
            height: 100vh; 
            font-family: "Fira Sans", sans-serif;
            color: #333;
            margin: 0 auto;
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
            margin: 3px;
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
    <a class="back" href="index.php">Назад</a>
    <h1 class="editing">Редактирование посылки</h1>
    <?php if (!empty($errors)): ?>
        <div class="error" style="color: red; text-align: center;">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <form class="post" method="POST">

    <select class="post__input" name="sending" <?= !$is_editable ? 'disabled' : '' ?> required>
        <option value="" disabled selected>Выберите город и отдел отправки</option>
        <?php foreach ($departments as $department): ?>
            <option value="<?= htmlspecialchars($department['id']) ?>" 
                <?= $shipment['sending_department_id'] == $department['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($department['city'] . ", " . $department['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php if (!$is_editable): ?>
        <input type="hidden" name="sending" value="<?= htmlspecialchars($shipment['sending_department_id']) ?>">
    <?php endif; ?>

    <select class="post__input" name="receiving" <?= !$is_editable ? 'disabled' : '' ?> required>
        <option value="" disabled selected>Выберите город и отдел получения</option>
        <?php foreach ($departments as $department): ?>
            <option value="<?= htmlspecialchars($department['id']) ?>" 
                <?= $shipment['receiving_department_id'] == $department['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($department['city'] . ", " . $department['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php if (!$is_editable): ?>
        <input type="hidden" name="receiving" value="<?= htmlspecialchars($shipment['receiving_department_id']) ?>">
    <?php endif; ?>

    <select class="post__input" name="status" required>
        <option value="" disabled selected>Статус</option>
        <option value="в обработке" <?= $shipment['status'] == 'в обработке' ? 'selected' : '' ?>>в обработке</option>
        <option value="в пути" <?= $shipment['status'] == 'в пути' ? 'selected' : '' ?>>в пути</option>
        <option value="доставлен" <?= $shipment['status'] == 'доставлен' ? 'selected' : '' ?>>доставлен</option>
    </select>

    <select class="post__input" name="package_type" <?= !$is_editable ? 'disabled' : '' ?> required>
        <option value="" disabled selected>Выберите тип доставки</option>
        <?php foreach ($package_types as $type): ?>
            <option value="<?= htmlspecialchars($type['id']) ?>" 
                <?= $shipment['package_type_id'] == $type['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($type['package_type']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php if (!$is_editable): ?>
        <input type="hidden" name="package_type" value="<?= htmlspecialchars($shipment['package_type_id']) ?>">
    <?php endif; ?>

    <select class="post__input" name="fast_delivery" <?= !$is_editable ? 'disabled' : '' ?> required>
        <option value="" disabled selected>Выберите нужна ли быстрая доставка</option>
        <?php foreach ($fast_delivery_options as $option): ?>
            <option value="<?= htmlspecialchars($option['id']) ?>" 
                <?= $shipment['fast_delivery_id'] == $option['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($option['fast_delivery'] == 1 ? 'Да' : 'Нет') ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php if (!$is_editable): ?>
        <input type="hidden" name="fast_delivery" value="<?= htmlspecialchars($shipment['fast_delivery_id']) ?>">
    <?php endif; ?>

    <button class="button" type="submit">Сохранить</button>
</form>

</body>
</html>
