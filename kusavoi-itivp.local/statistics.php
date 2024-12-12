<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Статистика по посылкам</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body{
            width: 90%;
            height: 100vh; 
            font-family: "Fira Sans", sans-serif;
            color: #333;
            margin: 0 auto;
        }
        .stat {
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
        select {
            width: 300px;
            height: 40px;
            border-radius: 15px;
            border: 1px solid #ccc;
            font-family: "Fira Sans", sans-serif;
            font-weight: 400;
            font-size: 15px;
            padding: 5px 10px;
            color: #333;
            background-color: #f9f9f9;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            outline: none;
            transition: 0.3s;
        }

        select:focus {
            border-color: #428ecf;
            box-shadow: 0 0 8px rgba(66, 142, 207, 0.8);
        }

        .buttonget[type="submit"] {
            height: 40px;
            width: 200px;
            font-size: 18px;
            font-family: "Fira Sans", sans-serif;
            font-weight: 400;
            color: white;
            background-image: linear-gradient(to right, #428ecf 0%, #0B63F6 51%, #428ecf 100%);
            background-size: 200% auto;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            border: none;
            cursor: pointer;
            transition: 0.5s;
        }

        .buttonget:hover {
            background-position: right center;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
        }
    </style>
</head>
<body>
    <a class="back" href="index.php">Назад</a>
    <h1 class="stat">Статистика по посылкам</h1>
    <form method="GET" action="statistics.php">
        <select id="column" name="column">
        <option value="" disabled selected>Выберите столбец для вывода</option>
        <option value="status">Статус</option>
        <option value="sending_city">Город отправки</option>
        <option value="receiving_city">Город получения</option>
        <option value="package_type_name">Тип посылки</option>
        <option value="fast_delivery">Быстрая доставка</option>
        </select>
        <button type="submit" class="buttonget">Показать статистику</button>
    </form>

    <?php include 'statisticsServer.php'; ?>
</body>
</html>