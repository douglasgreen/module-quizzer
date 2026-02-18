<?php

declare(strict_types=1);

namespace DouglasGreen\ModuleQuizzer\Repository;

use PDO;

/**
 * Persistence layer for the attempt table (quiz score records).
 */
final class AttemptRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function create(
        int $moduleId,
        float $score,
        int $totalQuestions,
        float $pointsEarned,
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO attempt (module_id, score, total_questions, points_earned)
             VALUES (:module_id, :score, :total_questions, :points_earned)',
        );
        $stmt->execute([
            'module_id'       => $moduleId,
            'score'           => $score,
            'total_questions' => $totalQuestions,
            'points_earned'   => $pointsEarned,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Retrieve all attempts joined with module and course titles for reporting.
     *
     * @return list<array<string, mixed>>
     */
    public function findAllWithDetails(): array
    {
        $stmt = $this->pdo->query(
            'SELECT a.attempt_id, a.score, a.total_questions, a.points_earned, a.attempted_at,
                    m.title  AS module_title, m.module_id,
                    c.title  AS course_title, c.course_id
             FROM attempt a
             JOIN module m ON a.module_id = m.module_id
             JOIN course c ON m.course_id = c.course_id
             ORDER BY a.attempted_at DESC',
        );
        return $stmt->fetchAll();
    }
}
