<?php

declare(strict_types=1);

namespace DouglasGreen\ModuleQuizzer\Repository;

use PDO;

/**
 * Persistence layer for the lesson table (one lesson per module).
 */
final class LessonRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function findByModuleId(int $moduleId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM lesson WHERE module_id = :module_id');
        $stmt->execute(['module_id' => $moduleId]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Insert or update the lesson for a module (upsert).
     */
    public function save(int $moduleId, string $contentHtml): void
    {
        $existing = $this->findByModuleId($moduleId);

        if ($existing !== null) {
            $stmt = $this->pdo->prepare(
                'UPDATE lesson SET content_html = :content_html WHERE module_id = :module_id',
            );
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO lesson (module_id, content_html) VALUES (:module_id, :content_html)',
            );
        }

        $stmt->execute(['module_id' => $moduleId, 'content_html' => $contentHtml]);
    }
}
