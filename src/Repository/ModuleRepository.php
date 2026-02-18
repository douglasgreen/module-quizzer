<?php

declare(strict_types=1);

namespace DouglasGreen\ModuleQuizzer\Repository;

use PDO;

/**
 * Persistence layer for the module table.
 */
final class ModuleRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function findByCourseId(int $courseId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM module WHERE course_id = :course_id ORDER BY sort_order ASC, module_id ASC',
        );
        $stmt->execute(['course_id' => $courseId]);
        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM module WHERE module_id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    public function create(int $courseId, string $title, int $sortOrder): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO module (course_id, title, sort_order) VALUES (:course_id, :title, :sort_order)',
        );
        $stmt->execute([
            'course_id'  => $courseId,
            'title'      => $title,
            'sort_order' => $sortOrder,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, string $title, int $sortOrder): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE module SET title = :title, sort_order = :sort_order WHERE module_id = :id',
        );
        $stmt->execute(['id' => $id, 'title' => $title, 'sort_order' => $sortOrder]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM module WHERE module_id = :id');
        $stmt->execute(['id' => $id]);
    }
}
