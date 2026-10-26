<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/Exceptions.php';
require_once __DIR__ . '/Task.php';
require_once __DIR__ . '/TaskStorageInterface.php';
require_once __DIR__ . '/TaskRepository.php';

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$formErrors = [];
$feedbackMessage = $_SESSION['feedback'] ?? null;
unset($_SESSION['feedback']);

try {
    $repo = new TaskRepository(__DIR__ . '/tasks.json');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        try {
            switch ($action) {
                case 'add':
                    $title = (string) ($_POST['title'] ?? '');
                    $deadline = trim((string) ($_POST['deadline'] ?? ''));
                    $repo->add($title, $deadline !== '' ? $deadline : null);
                    $_SESSION['feedback'] = 'Завдання успішно додано!';
                    header('Location: index.php');
                    exit;

                case 'complete':
                    $repo->complete((int) ($_POST['id'] ?? 0));
                    $_SESSION['feedback'] = 'Статус завдання оновлено!';
                    header('Location: index.php');
                    exit;

                case 'delete':
                    $repo->delete((int) ($_POST['id'] ?? 0));
                    $_SESSION['feedback'] = 'Завдання видалено!';
                    header('Location: index.php');
                    exit;
            }
        } catch (ValidationException $e) {
            $formErrors = $e->getErrors();
        } catch (TaskNotFoundException $e) {
            $formErrors = [$e->getMessage()];
        } catch (TaskManagerException $e) {
            $formErrors = [$e->getMessage()];
        } catch (Throwable $e) {
            $formErrors = ['Сталася помилка під час обробки запиту.'];
        } finally {
            $requestHandled = true;
        }
    }

    $tasks = $repo->findAll();
} catch (Throwable $e) {
    $tasks = [];
    $formErrors[] = 'Не вдалося завантажити завдання. Перевірте файл tasks.json та права доступу до папки.';
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Менеджер завдань</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 760px;
            margin: 40px auto;
            padding: 0 20px;
            color: #222;
        }
        .stats {
            margin-bottom: 20px;
            padding: 12px;
            background: #f1f3f5;
            border-radius: 6px;
            font-weight: 500;
        }
        .alert-error {
            background: #ffe3e3;
            border: 1px solid #ffa8a8;
            color: #c92a2a;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .alert-error ul {
            margin: 6px 0 0 20px;
            padding: 0;
        }
        .alert-success {
            background: #d3f9d8;
            border: 1px solid #8ce99a;
            color: #2b8a3e;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .task {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        .task-meta {
            font-size: 0.85em;
            color: #868e96;
            margin-top: 4px;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            color: #fff;
            font-weight: bold;
        }
        .badge-success {
            background: #40c057;
        }
        .badge-danger {
            background: #fa5252;
        }
        .badge-primary {
            background: #228be6;
        }
        .form-add {
            display: flex;
            gap: 8px;
            margin-bottom: 25px;
        }
        .form-add input[type="text"] {
            flex-grow: 1;
            min-width: 0;
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .form-add input[type="date"] {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .form-add button {
            padding: 8px 16px;
            background: #228be6;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .actions {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-wrap: wrap;
        }
        .actions button {
            padding: 4px 8px;
            cursor: pointer;
            border-radius: 4px;
            border: 1px solid #ced4da;
        }
        @media (max-width: 600px) {
            .form-add {
                flex-direction: column;
            }
            .task {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<h1>Менеджер завдань</h1>

<p>
    Користувач:
    <strong><?= htmlspecialchars((string) ($_SESSION['user_name'] ?? 'Адміністратор'), ENT_QUOTES, 'UTF-8') ?></strong>
    |
    <a href="login.php">Вийти</a>
</p>

<?php if ($feedbackMessage): ?>
    <div class="alert-success">
        <?= htmlspecialchars((string) $feedbackMessage, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if ($formErrors !== []): ?>
    <div class="alert-error">
        <strong>Увага! Виявлено помилки під час виконання операції:</strong>
        <ul>
            <?php foreach ($formErrors as $error): ?>
                <li><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" action="index.php" class="form-add">
    <input type="hidden" name="action" value="add">
    <input
        type="text"
        name="title"
        placeholder="Що потрібно зробити (мінімум 3 символи)?"
        value="<?= htmlspecialchars((string) ($_POST['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
        required
        minlength="3"
    >
    <input
        type="date"
        name="deadline"
        value="<?= htmlspecialchars((string) ($_POST['deadline'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
    >
    <button type="submit">Додати</button>
</form>

<div class="stats">
    Всього завдань: <?= count($tasks) ?> |
    Виконано: <?= isset($repo) ? $repo->countCompleted() : 0 ?> |
    Прострочено: <?= isset($repo) ? $repo->countOverdue() : 0 ?>
</div>

<div class="tasks-list">
    <?php if ($tasks === []): ?>
        <p>Список завдань порожній.</p>
    <?php else: ?>
        <?php foreach ($tasks as $task): ?>
            <div class="task">
                <div>
                    <div>
                        <strong><?= htmlspecialchars($task->getTitle(), ENT_QUOTES, 'UTF-8') ?></strong>
                        <?php if ($task->getDeadline()): ?>
                            <small>
                                (Дедлайн: <?= htmlspecialchars($task->getDeadline(), ENT_QUOTES, 'UTF-8') ?>)
                            </small>
                        <?php endif; ?>
                    </div>

                    <div class="task-meta">
                        Створено: <?= htmlspecialchars($task->getCreatedAt(), ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($task->getUpdatedAt()): ?>
                            | Оновлено: <?= htmlspecialchars($task->getUpdatedAt(), ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="actions">
                    <?php
                    $badgeClass = match ($task->getStatusLabel()) {
                        'Виконано' => 'badge-success',
                        'Прострочено' => 'badge-danger',
                        default => 'badge-primary',
                    };
                    ?>

                    <span class="badge <?= $badgeClass ?>">
                        <?= htmlspecialchars($task->getStatusLabel(), ENT_QUOTES, 'UTF-8') ?>
                    </span>

                    <?php if (!$task->isCompleted()): ?>
                        <form method="POST" action="index.php" style="display:inline;">
                            <input type="hidden" name="action" value="complete">
                            <input type="hidden" name="id" value="<?= $task->getId() ?>">
                            <button type="submit" title="Позначити виконаним">✓</button>
                        </form>
                    <?php endif; ?>

                    <form method="POST" action="index.php" style="display:inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $task->getId() ?>">
                        <button type="submit" title="Видалити">✕</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>