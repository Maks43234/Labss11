<?php
session_start();

if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username === 'admin' && $password === '12345') {
        session_regenerate_id(true);
        $_SESSION['is_logged_in'] = true;
        $_SESSION['user_name'] = 'Адміністратор';

        header('Location: index.php');
        exit;
    } else {
        $error = 'Невірний логін або пароль!';
    }
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Вхід</title>
</head>
<body>
    <h1>Вхід у систему</h1>

    <?php if ($error !== ''): ?>
        <p style="color: red;">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <label>Логін:</label>
        <input type="text" name="username" required>

        <br><br>

        <label>Пароль:</label>
        <input type="password" name="password" required>

        <br><br>

        <button type="submit">Увійти</button>
    </form>

    <a href="index.php">На головну</a>
</body>
</html>