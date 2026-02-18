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
 * Exports a course (with modules, lessons, and questions) to an XML directory structure.
 *
 * Layout produced:
 *   <dir>/course.xml
 *   <dir>/module-1/module.xml
 *   <dir>/module-1/lesson.xml
 *   <dir>/module-1/question-001.xml
 */
final class XmlExporter
{
    public function __construct(
        private readonly CourseRepository $courseRepo,
        private readonly ModuleRepository $moduleRepo,
        private readonly LessonRepository $lessonRepo,
        private readonly QuestionRepository $questionRepo,
    ) {
    }

    public function exportCourse(int $courseId, string $outputDir): void
    {
        $course = $this->courseRepo->findById($courseId);
        if ($course === null) {
            throw new RuntimeException("Course not found: {$courseId}");
        }

        $this->ensureDir($outputDir);
        $this->writeCourseXml($course, $outputDir);

        $modules = $this->moduleRepo->findByCourseId($courseId);

        foreach ($modules as $index => $module) {
            $moduleDir = $outputDir . '/module-' . ($index + 1);
            $this->ensureDir($moduleDir);
            $this->writeModuleXml($module, $moduleDir);

            $lesson = $this->lessonRepo->findByModuleId((int) $module['module_id']);
            if ($lesson !== null) {
                $this->writeLessonXml($lesson, $moduleDir);
            }

            $questions = $this->questionRepo->findByModuleId((int) $module['module_id']);
            foreach ($questions as $qIdx => $question) {
                $options = [];
                if (in_array($question['question_type'], ['multiple_choice', 'multiple_select'], true)) {
                    $options = $this->questionRepo->findOptionsByQuestionId((int) $question['question_id']);
                }
                $this->writeQuestionXml($question, $options, $moduleDir, $qIdx + 1);
            }
        }
    }

    /** @param array<string, mixed> $course */
    private function writeCourseXml(array $course, string $dir): void
    {
        $doc = $this->createDoc();
        $root = $doc->createElement('course');
        $doc->appendChild($root);
        $root->appendChild($doc->createElement('title', $course['title']));
        $root->appendChild($doc->createElement('description', $course['description'] ?? ''));
        $doc->save($dir . '/course.xml');
    }

    /** @param array<string, mixed> $module */
    private function writeModuleXml(array $module, string $dir): void
    {
        $doc = $this->createDoc();
        $root = $doc->createElement('module');
        $doc->appendChild($root);
        $root->appendChild($doc->createElement('title', $module['title']));
        $root->appendChild($doc->createElement('sort_order', (string) $module['sort_order']));
        $doc->save($dir . '/module.xml');
    }

    /** @param array<string, mixed> $lesson */
    private function writeLessonXml(array $lesson, string $dir): void
    {
        $doc = $this->createDoc();
        $root = $doc->createElement('lesson');
        $doc->appendChild($root);
        $content = $doc->createElement('content');
        $content->appendChild($doc->createCDATASection($lesson['content_html']));
        $root->appendChild($content);
        $doc->save($dir . '/lesson.xml');
    }

    /**
     * @param array<string, mixed>       $question
     * @param list<array<string, mixed>>  $options
     */
    private function writeQuestionXml(array $question, array $options, string $dir, int $number): void
    {
        $doc = $this->createDoc();
        $root = $doc->createElement('question');
        $root->setAttribute('type', $question['question_type']);
        $doc->appendChild($root);

        $root->appendChild($doc->createElement('prompt', $question['prompt']));
        $root->appendChild($doc->createElement('sort_order', (string) $question['sort_order']));

        switch ($question['question_type']) {
            case 'true_false':
                $root->appendChild(
                    $doc->createElement('correct_answer', $question['correct_boolean'] ? 'true' : 'false'),
                );
                break;

            case 'multiple_choice':
            case 'multiple_select':
                $optionsEl = $doc->createElement('options');
                foreach ($options as $opt) {
                    $optEl = $doc->createElement('option', $opt['option_text']);
                    $optEl->setAttribute('correct', $opt['is_correct'] ? 'true' : 'false');
                    $optionsEl->appendChild($optEl);
                }
                $root->appendChild($optionsEl);
                break;

            case 'fill_blank':
                $root->appendChild(
                    $doc->createElement('is_case_sensitive', $question['is_case_sensitive'] ? 'true' : 'false'),
                );
                $blanksEl = $doc->createElement('blanks');
                $answers = json_decode((string) ($question['fill_blank_answers'] ?? '[]'), true) ?: [];
                foreach ($answers as $group) {
                    $blankEl = $doc->createElement('blank');
                    foreach ($group as $answer) {
                        $blankEl->appendChild($doc->createElement('answer', $answer));
                    }
                    $blanksEl->appendChild($blankEl);
                }
                $root->appendChild($blanksEl);
                break;

            case 'flashcard':
                $root->appendChild($doc->createElement('answer', $question['flashcard_answer'] ?? ''));
                break;
        }

        if (! empty($question['feedback_correct'])) {
            $root->appendChild($doc->createElement('feedback_correct', $question['feedback_correct']));
        }
        if (! empty($question['feedback_incorrect'])) {
            $root->appendChild($doc->createElement('feedback_incorrect', $question['feedback_incorrect']));
        }

        $filename = '/question-' . str_pad((string) $number, 3, '0', STR_PAD_LEFT) . '.xml';
        $doc->save($dir . $filename);
    }

    private function createDoc(): DOMDocument
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;
        return $doc;
    }

    private function ensureDir(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0775, true)) {
            throw new RuntimeException("Cannot create directory: {$path}");
        }
    }
}
