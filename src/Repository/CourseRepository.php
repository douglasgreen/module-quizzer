<?php

declare(strict_types=1);

namespace DouglasGreen\ModuleQuizzer\Repository;

use PDO;

/**
 * Persistence layer for the course table.
 */
final class CourseRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM course ORDER BY title ASC');
        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM course WHERE course_id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    public function create(string $title, string $description): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO course (title, description) VALUES (:title, :description)',
        );
        $stmt->execute(['title' => $title, 'description' => $description]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, string $title, string $description): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE course SET title = :title, description = :description WHERE course_id = :id',
        );
        $stmt->execute(['id' => $id, 'title' => $title, 'description' => $description]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM course WHERE course_id = :id');
        $stmt->execute(['id' => $id]);
    }
}
