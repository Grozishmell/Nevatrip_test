<?php

function generateUniqueBarcode($existingBarcodes) {
    do {
        // Генерируем случайный штрих-код из 8 цифр
        $barcode = str_pad(rand(0, 99999999), 8, '0', STR_PAD_LEFT);
    } while (in_array($barcode, $existingBarcodes)); // Проверяем уникальность
    return $barcode;
}

function bookOrder($event_id, $event_date, $ticket_adult_price, $ticket_adult_quantity, $ticket_kid_price, $ticket_kid_quantity, $pdo) {
    // Получаем существующие штрих-коды из базы данных для проверки уникальности
    $stmt = $pdo->query("SELECT barcode FROM orders");
    $existingBarcodes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Генерируем уникальный штрих-код
    $barcode = generateUniqueBarcode($existingBarcodes);

    // Подготовка данных для запроса
    $data = [
        'event_id' => $event_id,
        'event_date' => $event_date,
        'ticket_adult_price' => $ticket_adult_price,
        'ticket_adult_quantity' => $ticket_adult_quantity,
        'ticket_kid_price' => $ticket_kid_price,
        'ticket_kid_quantity' => $ticket_kid_quantity,
        'barcode' => $barcode,
    ];

    // Бронирование заказа
    $response = sendApiRequest('https://api.site.com/book', $data);
    
    // Проверка ответа
    if (isset($response['error'])) {
        if ($response['error'] === 'barcode already exists') {
            // Генерируем новый штрих-код и пробуем снова
            return bookOrder($event_id, $event_date, $ticket_adult_price, $ticket_adult_quantity, $ticket_kid_price, $ticket_kid_quantity, $pdo);
        }
    } elseif (isset($response['message']) && $response['message'] === 'order successfully booked') {
        // Подтверждаем бронь
        $approveResponse = sendApiRequest('https://api.site.com/approve', ['barcode' => $barcode]);

        // Проверка ответа на подтверждение
        if (isset($approveResponse['message']) && $approveResponse['message'] === 'order successfully aproved') {
            // Сохраняем заказ в базе данных
            saveOrderToDatabase($event_id, $event_date, $ticket_adult_price, $ticket_adult_quantity, $ticket_kid_price, $ticket_kid_quantity, $barcode, $pdo);
            return true;
        } else {
            // Обработка ошибок подтверждения
            echo "Ошибка подтверждения: " . $approveResponse['error'];
            return false;
        }
    }
    return false;
}

function sendApiRequest($url, $data) {
    // Симуляция отправки запроса на API
    // Здесь вы можете использовать cURL для реального запроса
    // Для примера, просто возвращаем случайный результат
    $responses = [
        ['message' => 'order successfully booked'],
        ['error' => 'barcode already exists'],
        ['message' => 'order successfully aproved'],
        ['error' => 'event cancelled'],
        ['error' => 'no tickets'],
        ['error' => 'no seats'],
        ['error' => 'fan removed'],
    ];
    return $responses[array_rand($responses)];
}

function saveOrderToDatabase($event_id, $event_date, $ticket_adult_price, $ticket_adult_quantity, $ticket_kid_price, $ticket_kid_quantity, $barcode, $pdo) {
    $equal_price = ($ticket_adult_price * $ticket_adult_quantity) + ($ticket_kid_price * $ticket_kid_quantity);
    $created = date('Y-m-d H:i:s');
    
    $stmt = $pdo->prepare("INSERT INTO orders (event_id, event_date, ticket_adult_price, ticket_adult_quantity, ticket_kid_price, ticket_kid_quantity, barcode, equal_price, created) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$event_id, $event_date, $ticket_adult_price, $ticket_adult_quantity, $ticket_kid_price, $ticket_kid_quantity, $barcode, $equal_price, $created]);
}

// Пример использования функции
try {
    $pdo = new PDO('mysql:host=localhost;dbname=your_database', 'username', 'password');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    bookOrder(3, '2021-08-21 13:00:00', 700, 1, 450, 0, $pdo);
} catch (PDOException $e) {
    echo 'Ошибка подключения: ' . $e->getMessage();
}
?>
