<?php
if (isset($_GET['column'])) {
    // Подключение к базе данных
    try {
        include 'db.php';
    } catch (PDOException $e) {
        header('Location: index.php');
        exit();
    }

    $column = $_GET['column'];

    // Проверяем, чтобы выбранный столбец был допустимым
    $allowed_columns = ['status', 'sending_city', 'receiving_city', 'package_type_name', 'fast_delivery'];
    if (!in_array($column, $allowed_columns)) {
        echo "<p>Ошибка: недопустимый столбец.</p>";
        exit;
    }

    // Получаем ID департамента текущего пользователя
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT department_id FROM users WHERE id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    $department = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$department) {
        echo "<p>Ошибка: отдел пользователя не найден.</p>";
        exit;
    }

    $department_id = $department['department_id'];

    // Выполняем запросы в зависимости от выбранного столбца
    switch ($column) {
        case 'status':
            $query = "
                SELECT s.status AS label, COUNT(*) AS count
                FROM shipments s
                WHERE s.sending_department_id = :department_id OR s.receiving_department_id = :department_id
                GROUP BY s.status
            ";
            break;
        case 'sending_city':
            $query = "
                SELECT sd.city AS label, COUNT(*) AS count
                FROM shipments s
                JOIN departments sd ON s.sending_department_id = sd.id
                WHERE s.sending_department_id = :department_id OR s.receiving_department_id = :department_id
                GROUP BY sd.city
            ";
            break;
        case 'receiving_city':
            $query = "
                SELECT rd.city AS label, COUNT(*) AS count
                FROM shipments s
                JOIN departments rd ON s.receiving_department_id = rd.id
                WHERE s.sending_department_id = :department_id OR s.receiving_department_id = :department_id
                GROUP BY rd.city
            ";
            break;
        case 'package_type_name':
            $query = "
                SELECT st.package_type AS label, COUNT(*) AS count
                FROM shipments s
                LEFT JOIN shipment_types st ON s.package_type_id = st.id
                WHERE s.sending_department_id = :department_id OR s.receiving_department_id = :department_id
                GROUP BY st.package_type
            ";
            break;
        case 'fast_delivery':
            $query = "
                SELECT 
                CASE 
                WHEN sd2.fast_delivery = 1 THEN 'Да' 
                WHEN sd2.fast_delivery = 0 THEN 'Нет' 
                ELSE 'Не определено' 
                END AS label, 
                COUNT(*) AS count
                FROM shipments s
                LEFT JOIN shipment_delivery sd2 ON s.fast_delivery_id = sd2.id
                WHERE s.sending_department_id = :department_id OR s.receiving_department_id = :department_id
                GROUP BY sd2.fast_delivery
            ";
            break;
        default:
            echo "<p>Ошибка: недопустимый столбец.</p>";
            exit;
    }

    // Выполняем запрос
    $stmt = $pdo->prepare($query);
    $stmt->execute(['department_id' => $department_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Подготовка данных для графика
    $labels = [];
    $counts = [];
    foreach ($results as $row) {
        $labels[] = $row['label'];
        $counts[] = $row['count'];
    }

    // Преобразуем данные в формат JSON
    $labels_json = json_encode($labels);
    $counts_json = json_encode($counts);
} else {
    echo "<p>Выберите столбец для отображения статистики.</p>";
    exit;
}

?>
<!DOCTYPE html>
<html>
<body>
<!-- Отображение графика -->
<canvas id="statisticsChart"></canvas>
<script>
    const labels = <?php echo $labels_json; ?>;
    const counts = <?php echo $counts_json; ?>;

    function getRandomColor() {
        const r = Math.floor(Math.random() * 256);  // Красный (0-255)
        const g = Math.floor(Math.random() * 256);  // Зеленый (0-255)
        const b = Math.floor(Math.random() * 256);  // Синий (0-255)
        const a = 0.8;  // Прозрачность (можно изменить по желанию)
        return `rgba(${r}, ${g}, ${b}, ${a})`;
    }

    // Создаем массив случайных цветов для каждого значения
    const backgroundColors = counts.map(() => getRandomColor());
    const borderColors = backgroundColors.map(color => color.replace('0.2', '1'));

    const ctx = document.getElementById('statisticsChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Количество',
                data: counts,
                backgroundColor: backgroundColors,  // Цвета для каждого значения
                borderColor: backgroundColors.map(color => color.replace('0.2', '1')),  // Изменяем альфа-канал для границ
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,  // Сделать график адаптивным
            maintainAspectRatio: false,  // Разрешить изменять пропорции
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
</script>

<style>
    #statisticsChart {
        max-width: 1550px;  
        max-height: 650px;  
        width: 100%;       
        height: auto; 
        margin: auto;
        margin-top: 20px;      
    }
</style>
</body>
</html>

