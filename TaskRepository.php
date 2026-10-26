<?php
declare(strict_types=1);

require_once __DIR__ . '/Task.php';
require_once __DIR__ . '/TaskStorageInterface.php';
require_once __DIR__ . '/Exceptions.php';

class TaskRepository implements TaskStorageInterface
{
    private array $tasks = [];

    public function __construct(private string $storageFile)
    {
        $this->load();
    }

    public function findAll(): array
    {
        return $this->tasks;
    }

    public function findById(int $id): ?Task
    {
        foreach ($this->tasks as $task) {
            if ($task->getId() === $id) {
                return $task;
            }
        }

        return null;
    }

    public function add(string $title, ?string $deadline = null): Task
    {
        $errors = [];
        $cleanTitle = trim($title);

        if ($cleanTitle === '') {
            $errors[] = 'Назва завдання є обов’язковою для заповнення.';
        } elseif (mb_strlen($cleanTitle) < 3) {
            $errors[] = 'Назва завдання повинна містити щонайменше 3 символи.';
        }

        if ($deadline !== null && $deadline !== '') {
            $timestamp = strtotime($deadline);

            if ($timestamp === false) {
                $errors[] = 'Вказано некоректну дату дедлайну.';
            } else {
                $deadline = date('Y-m-d', $timestamp);
            }
        } else {
            $deadline = null;
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $task = new Task(
            id: $this->generateNextId(),
            title: $cleanTitle,
            isCompleted: false,
            deadline: $deadline,
        );

        $this->tasks[] = $task;
        $this->save();

        return $task;
    }

    public function complete(int $id): bool
    {
        $task = $this->findById($id);

        if ($task === null) {
            throw new TaskNotFoundException($id);
        }

        $task->complete();
        $this->save();

        return true;
    }

    public function delete(int $id): bool
    {
        foreach ($this->tasks as $key => $task) {
            if ($task->getId() === $id) {
                unset($this->tasks[$key]);
                $this->tasks = array_values($this->tasks);
                $this->save();

                return true;
            }
        }

        throw new TaskNotFoundException($id);
    }

    public function countCompleted(): int
    {
        return count(array_filter(
            $this->tasks,
            fn(Task $task): bool => $task->isCompleted()
        ));
    }

    public function countOverdue(): int
    {
        return count(array_filter(
            $this->tasks,
            fn(Task $task): bool => $task->isOverdue()
        ));
    }

    private function generateNextId(): int
    {
        if ($this->tasks === []) {
            return 1;
        }

        return max(array_map(
            fn(Task $task): int => $task->getId(),
            $this->tasks
        )) + 1;
    }

    private function load(): void
    {
        if (!file_exists($this->storageFile)) {
            $this->tasks = [];
            return;
        }

        $json = file_get_contents($this->storageFile);

        if ($json === false || trim($json) === '') {
            $this->tasks = [];
            return;
        }

        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new TaskManagerException('Не вдалося прочитати файл завдань.');
        }

        $this->tasks = array_map(
            fn(array $item): Task => Task::fromArray($item),
            $data
        );
    }

    private function save(): void
    {
        $data = array_map(
            fn(Task $task): array => $task->toArray(),
            $this->tasks
        );

        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );

        if ($json === false || file_put_contents($this->storageFile, $json, LOCK_EX) === false) {
            throw new TaskManagerException('Не вдалося зберегти завдання.');
        }
    }
}