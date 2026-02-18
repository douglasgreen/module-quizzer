<?php

declare(strict_types=1);

namespace DouglasGreen\ModuleQuizzer\Service;

use Normalizer;

/**
 * Deterministic grading logic for all question types.
 *
 * Every question is worth a maximum of 1.0 points so that each question
 * counts equally in the final score.
 *
 * Grading rules (versioned rubric):
 *  - True/False:       1.0 if correct, 0.0 otherwise.
 *  - Multiple Choice:  1.0 if selected option is correct, 0.0 otherwise.
 *  - Multiple Select:  Partial credit: max(0, c/C − 0.5·w/W).
 *  - Fill-in-Blank:    Partial credit per blank (correct blanks / total blanks).
 *  - Flashcard:        Self-graded (1.0 or 0.0).
 */
final class GradingService
{
    public function gradeTrueFalse(bool $answer, bool $correct): float
    {
        return $answer === $correct ? 1.0 : 0.0;
    }

    public function gradeMultipleChoice(int $selectedOptionId, int $correctOptionId): float
    {
        return $selectedOptionId === $correctOptionId ? 1.0 : 0.0;
    }

    /**
     * Grade a multiple-select question using partial-credit formula:
     *
     * $$\text{score} = \max\!\left(0,\;\frac{c}{C} - 0.5 \cdot \frac{w}{W}\right)$$
     *
     * @param list<int> $selectedIds  Option IDs the learner selected
     * @param list<int> $correctIds   Option IDs that are correct
     * @param list<int> $allOptionIds All option IDs for this question
     */
    public function gradeMultipleSelect(
        array $selectedIds,
        array $correctIds,
        array $allOptionIds,
    ): float {
        $totalCorrect = count($correctIds);
        $totalWrong   = count($allOptionIds) - $totalCorrect;

        if ($totalCorrect === 0) {
            return 0.0;
        }

        $correctSelected = count(array_intersect($selectedIds, $correctIds));
        $wrongSelected   = count(array_diff($selectedIds, $correctIds));

        $score = $correctSelected / $totalCorrect;

        if ($totalWrong > 0) {
            $score -= 0.5 * ($wrongSelected / $totalWrong);
        }

        return max(0.0, round($score, 4));
    }

    /**
     * Grade fill-in-blank answers.
     *
     * Each blank is scored independently; the result is correct_blanks / total_blanks.
     * Answers are normalised: trimmed, Unicode NFKC, optional case folding.
     *
     * @param list<string>        $userAnswers     One answer per blank
     * @param list<list<string>>  $acceptedAnswers Accepted answers per blank position
     */
    public function gradeFillBlank(
        array $userAnswers,
        array $acceptedAnswers,
        bool $caseSensitive = false,
    ): float {
        $totalBlanks = count($acceptedAnswers);

        if ($totalBlanks === 0) {
            return 0.0;
        }

        $correctBlanks = 0;

        foreach ($acceptedAnswers as $index => $accepted) {
            $raw = trim($userAnswers[$index] ?? '');
            $normalised = $this->normalise($raw, $caseSensitive);

            foreach ($accepted as $validAnswer) {
                $validNormalised = $this->normalise(trim($validAnswer), $caseSensitive);

                if ($normalised === $validNormalised) {
                    ++$correctBlanks;
                    break;
                }
            }
        }

        return round($correctBlanks / $totalBlanks, 4);
    }

    public function gradeFlashcard(bool $selfCorrect): float
    {
        return $selfCorrect ? 1.0 : 0.0;
    }

    /**
     * Normalise a string for comparison: trim, NFKC, optional case fold.
     */
    private function normalise(string $value, bool $caseSensitive): string
    {
        $value = trim($value);

        if (class_exists(Normalizer::class)) {
            $value = Normalizer::normalize($value, Normalizer::FORM_KC) ?: $value;
        }

        if (! $caseSensitive) {
            $value = mb_strtolower($value, 'UTF-8');
        }

        return $value;
    }
}
