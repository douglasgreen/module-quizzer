<?php

declare(strict_types=1);

namespace DouglasGreen\ModuleQuizzer\Repository;

use PDO;

/**
 * Persistence layer for the question and question_option tables.
 */
final class QuestionRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function findByModuleId(int $moduleId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM question WHERE module_id = :module_id
             ORDER BY sort_order ASC, question_id ASC',
        );
        $stmt->execute(['module_id' => $moduleId]);
        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM question WHERE question_id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Create a new question.
     *
     * @param list<list<string>>|null $fillBlankAnswers Accepted answers per blank position
     */
    public function create(
        int $moduleId,
        string $questionType,
        string $prompt,
        int $sortOrder,
        ?bool $correctBoolean = null,
        ?string $flashcardAnswer = null,
        ?array $fillBlankAnswers = null,
        bool $isCaseSensitive = false,
        ?string $feedbackCorrect = null,
        ?string $feedbackIncorrect = null,
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO question
                (module_id, question_type, prompt, sort_order,
                 correct_boolean, flashcard_answer, fill_blank_answers,
                 is_case_sensitive, feedback_correct, feedback_incorrect)
             VALUES
                (:module_id, :question_type, :prompt, :sort_order,
                 :correct_boolean, :flashcard_answer, :fill_blank_answers,
                 :is_case_sensitive, :feedback_correct, :feedback_incorrect)',
        );
        $stmt->execute([
            'module_id'          => $moduleId,
            'question_type'      => $questionType,
            'prompt'             => $prompt,
            'sort_order'         => $sortOrder,
            'correct_boolean'    => $correctBoolean !== null ? (int) $correctBoolean : null,
            'flashcard_answer'   => $flashcardAnswer,
            'fill_blank_answers' => $fillBlankAnswers !== null ? json_encode($fillBlankAnswers) : null,
            'is_case_sensitive'  => (int) $isCaseSensitive,
            'feedback_correct'   => $feedbackCorrect,
            'feedback_incorrect' => $feedbackIncorrect,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update an existing question.
     *
     * @param list<list<string>>|null $fillBlankAnswers
     */
    public function update(
        int $id,
        string $questionType,
        string $prompt,
        int $sortOrder,
        ?bool $correctBoolean = null,
        ?string $flashcardAnswer = null,
        ?array $fillBlankAnswers = null,
        bool $isCaseSensitive = false,
        ?string $feedbackCorrect = null,
        ?string $feedbackIncorrect = null,
    ): void {
        $stmt = $this->pdo->prepare(
            'UPDATE question SET
                question_type      = :question_type,
                prompt             = :prompt,
                sort_order         = :sort_order,
                correct_boolean    = :correct_boolean,
                flashcard_answer   = :flashcard_answer,
                fill_blank_answers = :fill_blank_answers,
                is_case_sensitive  = :is_case_sensitive,
                feedback_correct   = :feedback_correct,
                feedback_incorrect = :feedback_incorrect
             WHERE question_id = :id',
        );
        $stmt->execute([
            'id'                 => $id,
            'question_type'      => $questionType,
            'prompt'             => $prompt,
            'sort_order'         => $sortOrder,
            'correct_boolean'    => $correctBoolean !== null ? (int) $correctBoolean : null,
            'flashcard_answer'   => $flashcardAnswer,
            'fill_blank_answers' => $fillBlankAnswers !== null ? json_encode($fillBlankAnswers) : null,
            'is_case_sensitive'  => (int) $isCaseSensitive,
            'feedback_correct'   => $feedbackCorrect,
            'feedback_incorrect' => $feedbackIncorrect,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM question WHERE question_id = :id');
        $stmt->execute(['id' => $id]);
    }

    // ── Option methods ────────────────────────────────────────

    public function addOption(int $questionId, string $text, bool $isCorrect, int $sortOrder): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO question_option (question_id, option_text, is_correct, sort_order)
             VALUES (:question_id, :option_text, :is_correct, :sort_order)',
        );
        $stmt->execute([
            'question_id' => $questionId,
            'option_text'  => $text,
            'is_correct'   => (int) $isCorrect,
            'sort_order'   => $sortOrder,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function deleteOptions(int $questionId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM question_option WHERE question_id = :id');
        $stmt->execute(['id' => $questionId]);
    }

    /** @return list<array<string, mixed>> */
    public function findOptionsByQuestionId(int $questionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM question_option WHERE question_id = :id ORDER BY sort_order ASC',
        );
        $stmt->execute(['id' => $questionId]);
        return $stmt->fetchAll();
    }
}
