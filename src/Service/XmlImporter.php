<?php

declare(strict_types=1);

namespace DouglasGreen\ModuleQuizzer\Service;

use DOMDocument;
use DouglasGreen\ModuleQuizzer\Repository\CourseRepository;
use DouglasGreen\ModuleQuizzer\Repository\LessonRepository;
use DouglasGreen\ModuleQuizzer\Repository\ModuleRepository;
use DouglasGreen\ModuleQuizzer\Repository\QuestionRepository;
use RuntimeException;

/**
 * Imports a course from an XML directory structure (reverse of XmlExporter).
 */
final class XmlImporter
{
    public function __construct(
        private readonly CourseRepository $courseRepo,
        private readonly ModuleRepository $moduleRepo,
        private readonly LessonRepository $lessonRepo,
        private readonly QuestionRepository $questionRepo,
    ) {
    }

    /**
     * Import a full course from the given directory.
     *
     * @return int The newly created course ID
     */
    public function importCourse(string $inputDir): int
    {
        $coursePath = $inputDir . '/course.xml';
        if (! file_exists($coursePath)) {
            throw new RuntimeException("course.xml not found in {$inputDir}");
        }

        $doc = $this->loadXml($coursePath);
        $title       = $this->text($doc, 'title');
        $description = $this->text($doc, 'description');
        $courseId     = $this->courseRepo->create($title, $description);

        $dirs = glob($inputDir . '/module-*', GLOB_ONLYDIR) ?: [];
        sort($dirs, SORT_NATURAL);

        foreach ($dirs as $moduleDir) {
            $this->importModule($courseId, $moduleDir);
        }

        return $courseId;
    }

    private function importModule(int $courseId, string $moduleDir): void
    {
        $modulePath = $moduleDir . '/module.xml';
        if (! file_exists($modulePath)) {
            throw new RuntimeException("module.xml not found in {$moduleDir}");
        }

        $doc       = $this->loadXml($modulePath);
        $title     = $this->text($doc, 'title');
        $sortOrder = (int) $this->text($doc, 'sort_order');
        $moduleId  = $this->moduleRepo->create($courseId, $title, $sortOrder);

        $lessonPath = $moduleDir . '/lesson.xml';
        if (file_exists($lessonPath)) {
            $lDoc        = $this->loadXml($lessonPath);
            $contentNode = $lDoc->getElementsByTagName('content')->item(0);
            $contentHtml = $contentNode !== null ? $contentNode->textContent : '';
            $this->lessonRepo->save($moduleId, $contentHtml);
        }

        $qFiles = glob($moduleDir . '/question-*.xml') ?: [];
        sort($qFiles, SORT_NATURAL);

        foreach ($qFiles as $qPath) {
            $this->importQuestion($moduleId, $qPath);
        }
    }

    private function importQuestion(int $moduleId, string $path): void
    {
        $doc  = $this->loadXml($path);
        $root = $doc->documentElement;

        if ($root === null) {
            throw new RuntimeException("Invalid question XML: {$path}");
        }

        $type      = $root->getAttribute('type');
        $prompt    = $this->text($doc, 'prompt');
        $sortOrder = (int) $this->text($doc, 'sort_order');
        $fbCorrect = $this->text($doc, 'feedback_correct') ?: null;
        $fbWrong   = $this->text($doc, 'feedback_incorrect') ?: null;

        $correctBoolean   = null;
        $flashcardAnswer  = null;
        $fillBlankAnswers = null;
        $isCaseSensitive  = false;

        switch ($type) {
            case 'true_false':
                $correctBoolean = $this->text($doc, 'correct_answer') === 'true';
                break;

            case 'fill_blank':
                $isCaseSensitive = $this->text($doc, 'is_case_sensitive') === 'true';
                $fillBlankAnswers = [];
                $blanks = $doc->getElementsByTagName('blank');
                for ($i = 0; $i < $blanks->length; $i++) {
                    $blank   = $blanks->item($i);
                    $answers = [];
                    if ($blank !== null) {
                        $ansEls = $blank->getElementsByTagName('answer');
                        for ($j = 0; $j < $ansEls->length; $j++) {
                            $answers[] = $ansEls->item($j)?->textContent ?? '';
                        }
                    }
                    $fillBlankAnswers[] = $answers;
                }
                break;

            case 'flashcard':
                $flashcardAnswer = $this->text($doc, 'answer');
                break;
        }

        $questionId = $this->questionRepo->create(
            $moduleId,
            $type,
            $prompt,
            $sortOrder,
            $correctBoolean,
            $flashcardAnswer,
            $fillBlankAnswers,
            $isCaseSensitive,
            $fbCorrect,
            $fbWrong,
        );

        if (in_array($type, ['multiple_choice', 'multiple_select'], true)) {
            $optEls = $doc->getElementsByTagName('option');
            for ($i = 0; $i < $optEls->length; $i++) {
                $optEl = $optEls->item($i);
                if ($optEl === null) {
                    continue;
                }
                $text      = $optEl->textContent;
                $isCorrect = $optEl->getAttribute('correct') === 'true';
                $this->questionRepo->addOption($questionId, $text, $isCorrect, $i);
            }
        }
    }

    private function loadXml(string $path): DOMDocument
    {
        $doc = new DOMDocument();
        $doc->load($path);
        return $doc;
    }

    private function text(DOMDocument $doc, string $tag): string
    {
        $node = $doc->getElementsByTagName($tag)->item(0);
        return $node !== null ? trim($node->textContent) : '';
    }
}
