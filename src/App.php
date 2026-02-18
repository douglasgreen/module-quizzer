<?php

declare(strict_types=1);

namespace DouglasGreen\ModuleQuizzer;

use DouglasGreen\ModuleQuizzer\Config\DatabaseConfig;
use DouglasGreen\ModuleQuizzer\Repository\AttemptRepository;
use DouglasGreen\ModuleQuizzer\Repository\CourseRepository;
use DouglasGreen\ModuleQuizzer\Repository\LessonRepository;
use DouglasGreen\ModuleQuizzer\Repository\ModuleRepository;
use DouglasGreen\ModuleQuizzer\Repository\QuestionRepository;
use DouglasGreen\ModuleQuizzer\Service\GradingService;
use PDO;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * Front controller: bootstraps services, routes HTTP requests, handles all actions.
 */
final class App
{
    private readonly PDO $pdo;

    private readonly Environment $twig;

    private readonly CourseRepository $courseRepo;

    private readonly ModuleRepository $moduleRepo;

    private readonly LessonRepository $lessonRepo;

    private readonly QuestionRepository $questionRepo;

    private readonly AttemptRepository $attemptRepo;

    private readonly GradingService $gradingService;

    public function __construct()
    {
        $root       = dirname(__DIR__);
        $config     = DatabaseConfig::load($root . '/config/database.yaml');
        $this->pdo  = $config->createPdo();

        $cacheDir = $root . '/var/cache/twig';
        if (! is_dir($cacheDir)) {
            mkdir($cacheDir, 0775, true);
        }

        $loader     = new FilesystemLoader($root . '/templates');
        $this->twig = new Environment($loader, [
            'cache'       => $cacheDir,
            'auto_reload' => true,
            'strict_variables' => true,
        ]);

        $this->twig->addFunction(new TwigFunction('csrf_token', static function (): string {
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            return $_SESSION['csrf_token'];
        }));

        $this->courseRepo     = new CourseRepository($this->pdo);
        $this->moduleRepo     = new ModuleRepository($this->pdo);
        $this->lessonRepo     = new LessonRepository($this->pdo);
        $this->questionRepo   = new QuestionRepository($this->pdo);
        $this->attemptRepo    = new AttemptRepository($this->pdo);
        $this->gradingService = new GradingService();
    }

    public function run(): void
    {
        session_start();

        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri    = rtrim((string) $uri, '/') ?: '/';

        $this->dispatch($method, $uri);
    }

    // ── Routing ───────────────────────────────────────────────────────

    private function dispatch(string $method, string $uri): void
    {
        $routes = [
            ['GET',  '#^/$#',                              'courseList'],
            ['GET',  '#^/course/create$#',                 'courseCreate'],
            ['POST', '#^/course/create$#',                 'courseStore'],
            ['GET',  '#^/course/(\d+)$#',                  'courseShow'],
            ['GET',  '#^/course/(\d+)/edit$#',             'courseEdit'],
            ['POST', '#^/course/(\d+)/edit$#',             'courseUpdate'],
            ['POST', '#^/course/(\d+)/delete$#',           'courseDelete'],
            ['GET',  '#^/course/(\d+)/module/create$#',    'moduleCreate'],
            ['POST', '#^/course/(\d+)/module/create$#',    'moduleStore'],
            ['GET',  '#^/module/(\d+)$#',                  'moduleShow'],
            ['GET',  '#^/module/(\d+)/edit$#',             'moduleEdit'],
            ['POST', '#^/module/(\d+)/edit$#',             'moduleUpdate'],
            ['POST', '#^/module/(\d+)/delete$#',           'moduleDelete'],
            ['GET',  '#^/module/(\d+)/lesson$#',           'lessonEdit'],
            ['POST', '#^/module/(\d+)/lesson$#',           'lessonSave'],
            ['GET',  '#^/module/(\d+)/question/create$#',  'questionCreate'],
            ['POST', '#^/module/(\d+)/question/create$#',  'questionStore'],
            ['GET',  '#^/question/(\d+)/edit$#',           'questionEditForm'],
            ['POST', '#^/question/(\d+)/edit$#',           'questionUpdate'],
            ['POST', '#^/question/(\d+)/delete$#',         'questionDelete'],
            ['GET',  '#^/module/(\d+)/quiz$#',             'quizTake'],
            ['POST', '#^/module/(\d+)/quiz$#',             'quizSubmit'],
            ['GET',  '#^/report$#',                        'reportIndex'],
        ];

        foreach ($routes as [$routeMethod, $pattern, $handler]) {
            if ($method !== $routeMethod) {
                continue;
            }
            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches);
                $args = array_map('intval', $matches);
                $this->{$handler}(...$args);
                return;
            }
        }

        $this->notFound();
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /** @param array<string, mixed> $context */
    private function render(string $template, array $context = []): string
    {
        $context['flash_messages'] = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $this->twig->render($template, $context);
    }

    private function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    private function validateCsrf(): void
    {
        $token = $_POST['csrf_token'] ?? '';
        if (! hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(403);
            echo $this->render('error/404.html.twig');
            exit;
        }
    }

    private function notFound(): never
    {
        http_response_code(404);
        echo $this->render('error/404.html.twig');
        exit;
    }

    // ── Course Handlers ───────────────────────────────────────────────

    private function courseList(): void
    {
        $courses = $this->courseRepo->findAll();
        echo $this->render('course/index.html.twig', ['courses' => $courses]);
    }

    private function courseCreate(): void
    {
        echo $this->render('course/form.html.twig', [
            'course'  => null,
            'is_edit' => false,
        ]);
    }

    private function courseStore(): void
    {
        $this->validateCsrf();

        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($title === '') {
            $this->flash('danger', 'Course title is required.');
            $this->redirect('/course/create');
        }

        $id = $this->courseRepo->create($title, $description);
        $this->flash('success', 'Course created successfully.');
        $this->redirect('/course/' . $id);
    }

    private function courseShow(int $id): void
    {
        $course = $this->courseRepo->findById($id);
        if ($course === null) {
            $this->notFound();
        }

        $modules = $this->moduleRepo->findByCourseId($id);

        echo $this->render('course/show.html.twig', [
            'course'  => $course,
            'modules' => $modules,
        ]);
    }

    private function courseEdit(int $id): void
    {
        $course = $this->courseRepo->findById($id);
        if ($course === null) {
            $this->notFound();
        }

        echo $this->render('course/form.html.twig', [
            'course'  => $course,
            'is_edit' => true,
        ]);
    }

    private function courseUpdate(int $id): void
    {
        $this->validateCsrf();

        $course = $this->courseRepo->findById($id);
        if ($course === null) {
            $this->notFound();
        }

        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($title === '') {
            $this->flash('danger', 'Course title is required.');
            $this->redirect('/course/' . $id . '/edit');
        }

        $this->courseRepo->update($id, $title, $description);
        $this->flash('success', 'Course updated.');
        $this->redirect('/course/' . $id);
    }

    private function courseDelete(int $id): void
    {
        $this->validateCsrf();
        $this->courseRepo->delete($id);
        $this->flash('success', 'Course deleted.');
        $this->redirect('/');
    }

    // ── Module Handlers ───────────────────────────────────────────────

    private function moduleCreate(int $courseId): void
    {
        $course = $this->courseRepo->findById($courseId);
        if ($course === null) {
            $this->notFound();
        }

        echo $this->render('module/form.html.twig', [
            'course'  => $course,
            'module'  => null,
            'is_edit' => false,
        ]);
    }

    private function moduleStore(int $courseId): void
    {
        $this->validateCsrf();

        $course = $this->courseRepo->findById($courseId);
        if ($course === null) {
            $this->notFound();
        }

        $title     = trim($_POST['title'] ?? '');
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);

        if ($title === '') {
            $this->flash('danger', 'Module title is required.');
            $this->redirect('/course/' . $courseId . '/module/create');
        }

        $moduleId = $this->moduleRepo->create($courseId, $title, $sortOrder);
        $this->flash('success', 'Module created.');
        $this->redirect('/module/' . $moduleId);
    }

    private function moduleShow(int $id): void
    {
        $module = $this->moduleRepo->findById($id);
        if ($module === null) {
            $this->notFound();
        }

        $course    = $this->courseRepo->findById((int) $module['course_id']);
        $lesson    = $this->lessonRepo->findByModuleId($id);
        $questions = $this->questionRepo->findByModuleId($id);

        echo $this->render('module/show.html.twig', [
            'course'    => $course,
            'module'    => $module,
            'lesson'    => $lesson,
            'questions' => $questions,
        ]);
    }

    private function moduleEdit(int $id): void
    {
        $module = $this->moduleRepo->findById($id);
        if ($module === null) {
            $this->notFound();
        }

        $course = $this->courseRepo->findById((int) $module['course_id']);

        echo $this->render('module/form.html.twig', [
            'course'  => $course,
            'module'  => $module,
            'is_edit' => true,
        ]);
    }

    private function moduleUpdate(int $id): void
    {
        $this->validateCsrf();

        $module = $this->moduleRepo->findById($id);
        if ($module === null) {
            $this->notFound();
        }

        $title     = trim($_POST['title'] ?? '');
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);

        if ($title === '') {
            $this->flash('danger', 'Module title is required.');
            $this->redirect('/module/' . $id . '/edit');
        }

        $this->moduleRepo->update($id, $title, $sortOrder);
        $this->flash('success', 'Module updated.');
        $this->redirect('/module/' . $id);
    }

    private function moduleDelete(int $id): void
    {
        $this->validateCsrf();

        $module = $this->moduleRepo->findById($id);
        if ($module === null) {
            $this->notFound();
        }

        $courseId = (int) $module['course_id'];
        $this->moduleRepo->delete($id);
        $this->flash('success', 'Module deleted.');
        $this->redirect('/course/' . $courseId);
    }

    // ── Lesson Handlers ───────────────────────────────────────────────

    private function lessonEdit(int $moduleId): void
    {
        $module = $this->moduleRepo->findById($moduleId);
        if ($module === null) {
            $this->notFound();
        }

        $course = $this->courseRepo->findById((int) $module['course_id']);
        $lesson = $this->lessonRepo->findByModuleId($moduleId);

        echo $this->render('lesson/form.html.twig', [
            'course' => $course,
            'module' => $module,
            'lesson' => $lesson,
        ]);
    }

    private function lessonSave(int $moduleId): void
    {
        $this->validateCsrf();

        $module = $this->moduleRepo->findById($moduleId);
        if ($module === null) {
            $this->notFound();
        }

        $contentHtml = $_POST['content_html'] ?? '';
        $this->lessonRepo->save($moduleId, $contentHtml);
        $this->flash('success', 'Lesson saved.');
        $this->redirect('/module/' . $moduleId);
    }

    // ── Question Handlers ─────────────────────────────────────────────

    private function questionCreate(int $moduleId): void
    {
        $module = $this->moduleRepo->findById($moduleId);
        if ($module === null) {
            $this->notFound();
        }

        $course = $this->courseRepo->findById((int) $module['course_id']);

        echo $this->render('question/form.html.twig', [
            'course'              => $course,
            'module'              => $module,
            'question'            => null,
            'options'             => [],
            'fill_blank_display'  => '',
            'is_edit'             => false,
        ]);
    }

    private function questionStore(int $moduleId): void
    {
        $this->validateCsrf();

        $module = $this->moduleRepo->findById($moduleId);
        if ($module === null) {
            $this->notFound();
        }

        $data = $this->parseQuestionForm();
        if ($data === null) {
            $this->flash('danger', 'Question type and prompt are required.');
            $this->redirect('/module/' . $moduleId . '/question/create');
        }

        $questionId = $this->questionRepo->create(
            $moduleId,
            $data['type'],
            $data['prompt'],
            $data['sort_order'],
            $data['correct_boolean'],
            $data['flashcard_answer'],
            $data['fill_blank_answers'],
            $data['is_case_sensitive'],
            $data['feedback_correct'],
            $data['feedback_incorrect'],
        );

        $this->saveQuestionOptions($questionId, $data['type']);
        $this->flash('success', 'Question created.');
        $this->redirect('/module/' . $moduleId);
    }

    private function questionEditForm(int $questionId): void
    {
        $question = $this->questionRepo->findById($questionId);
        if ($question === null) {
            $this->notFound();
        }

        $module  = $this->moduleRepo->findById((int) $question['module_id']);
        $course  = $this->courseRepo->findById((int) $module['course_id']);
        $options = $this->questionRepo->findOptionsByQuestionId($questionId);

        // Build display string for fill-in-blank answers textarea
        $fillBlankDisplay = '';
        if (
            $question['question_type'] === 'fill_blank'
            && ! empty($question['fill_blank_answers'])
        ) {
            /** @var list<list<string>> $groups */
            $groups = json_decode((string) $question['fill_blank_answers'], true) ?: [];
            $parts  = [];
            foreach ($groups as $group) {
                $parts[] = implode("\n", $group);
            }
            $fillBlankDisplay = implode("\n---\n", $parts);
        }

        echo $this->render('question/form.html.twig', [
            'course'              => $course,
            'module'              => $module,
            'question'            => $question,
            'options'             => $options,
            'fill_blank_display'  => $fillBlankDisplay,
            'is_edit'             => true,
        ]);
    }

    private function questionUpdate(int $questionId): void
    {
        $this->validateCsrf();

        $question = $this->questionRepo->findById($questionId);
        if ($question === null) {
            $this->notFound();
        }

        $moduleId = (int) $question['module_id'];
        $data     = $this->parseQuestionForm();

        if ($data === null) {
            $this->flash('danger', 'Question type and prompt are required.');
            $this->redirect('/question/' . $questionId . '/edit');
        }

        $this->questionRepo->update(
            $questionId,
            $data['type'],
            $data['prompt'],
            $data['sort_order'],
            $data['correct_boolean'],
            $data['flashcard_answer'],
            $data['fill_blank_answers'],
            $data['is_case_sensitive'],
            $data['feedback_correct'],
            $data['feedback_incorrect'],
        );

        $this->questionRepo->deleteOptions($questionId);
        $this->saveQuestionOptions($questionId, $data['type']);

        $this->flash('success', 'Question updated.');
        $this->redirect('/module/' . $moduleId);
    }

    private function questionDelete(int $questionId): void
    {
        $this->validateCsrf();

        $question = $this->questionRepo->findById($questionId);
        if ($question === null) {
            $this->notFound();
        }

        $moduleId = (int) $question['module_id'];
        $this->questionRepo->delete($questionId);
        $this->flash('success', 'Question deleted.');
        $this->redirect('/module/' . $moduleId);
    }

    /**
     * Parse question form POST data into a normalised array.
     *
     * @return array{type: string, prompt: string, sort_order: int, correct_boolean: bool|null, flashcard_answer: string|null, fill_blank_answers: list<list<string>>|null, is_case_sensitive: bool, feedback_correct: string|null, feedback_incorrect: string|null}|null
     */
    private function parseQuestionForm(): ?array
    {
        $type   = trim($_POST['question_type'] ?? '');
        $prompt = trim($_POST['prompt'] ?? '');

        $validTypes = ['true_false', 'multiple_choice', 'multiple_select', 'fill_blank', 'flashcard'];

        if ($type === '' || $prompt === '' || ! in_array($type, $validTypes, true)) {
            return null;
        }

        $correctBoolean   = null;
        $flashcardAnswer  = null;
        $fillBlankAnswers = null;
        $isCaseSensitive  = false;

        switch ($type) {
            case 'true_false':
                $correctBoolean = ($_POST['correct_boolean'] ?? '0') === '1';
                break;
            case 'flashcard':
                $flashcardAnswer = trim($_POST['flashcard_answer'] ?? '');
                break;
            case 'fill_blank':
                $isCaseSensitive  = isset($_POST['is_case_sensitive']);
                $fillBlankAnswers = $this->parseFillBlankAnswers($_POST['fill_blank_answers'] ?? '');
                break;
        }

        return [
            'type'               => $type,
            'prompt'             => $prompt,
            'sort_order'         => (int) ($_POST['sort_order'] ?? 0),
            'correct_boolean'    => $correctBoolean,
            'flashcard_answer'   => $flashcardAnswer,
            'fill_blank_answers' => $fillBlankAnswers,
            'is_case_sensitive'  => $isCaseSensitive,
            'feedback_correct'   => trim($_POST['feedback_correct'] ?? '') ?: null,
            'feedback_incorrect' => trim($_POST['feedback_incorrect'] ?? '') ?: null,
        ];
    }

    /**
     * Parse the fill-in-blank answers textarea.
     *
     * Format: accepted answers separated by newlines; blanks separated by a line containing only ---.
     *
     * @return list<list<string>>
     */
    private function parseFillBlankAnswers(string $raw): array
    {
        $groups = preg_split('/^---$/m', $raw);
        if ($groups === false) {
            return [];
        }

        $result = [];
        foreach ($groups as $group) {
            $answers = array_values(array_filter(
                array_map('trim', explode("\n", trim($group))),
                static fn(string $line): bool => $line !== '',
            ));
            if ($answers !== []) {
                $result[] = $answers;
            }
        }

        return $result;
    }

    /**
     * Persist MCQ / MSQ options from POST data.
     */
    private function saveQuestionOptions(int $questionId, string $type): void
    {
        if (! in_array($type, ['multiple_choice', 'multiple_select'], true)) {
            return;
        }

        $texts        = $_POST['option_text'] ?? [];
        $correctFlags = $_POST['option_is_correct'] ?? [];

        foreach ($texts as $index => $text) {
            $text = trim((string) $text);
            if ($text === '') {
                continue;
            }
            $isCorrect = isset($correctFlags[$index]);
            $this->questionRepo->addOption($questionId, $text, $isCorrect, (int) $index);
        }
    }

    // ── Quiz Handlers ─────────────────────────────────────────────────

    private function quizTake(int $moduleId): void
    {
        $module = $this->moduleRepo->findById($moduleId);
        if ($module === null) {
            $this->notFound();
        }

        $course    = $this->courseRepo->findById((int) $module['course_id']);
        $questions = $this->questionRepo->findByModuleId($moduleId);

        if ($questions === []) {
            $this->flash('warning', 'This module has no questions yet.');
            $this->redirect('/module/' . $moduleId);
        }

        $optionsByQuestion = [];
        foreach ($questions as $q) {
            if (in_array($q['question_type'], ['multiple_choice', 'multiple_select'], true)) {
                $optionsByQuestion[$q['question_id']] =
                    $this->questionRepo->findOptionsByQuestionId((int) $q['question_id']);
            }
        }

        echo $this->render('quiz/take.html.twig', [
            'course'              => $course,
            'module'              => $module,
            'questions'           => $questions,
            'options_by_question' => $optionsByQuestion,
        ]);
    }

    private function quizSubmit(int $moduleId): void
    {
        $this->validateCsrf();

        $module = $this->moduleRepo->findById($moduleId);
        if ($module === null) {
            $this->notFound();
        }

        $course    = $this->courseRepo->findById((int) $module['course_id']);
        $questions = $this->questionRepo->findByModuleId($moduleId);
        $answers   = $_POST['answers'] ?? [];

        $results    = [];
        $totalScore = 0.0;

        foreach ($questions as $question) {
            $qid    = (string) $question['question_id'];
            $answer = $answers[$qid] ?? null;

            $score             = 0.0;
            $userAnswerText    = '(no answer)';
            $correctAnswerText = '';

            switch ($question['question_type']) {
                case 'true_false':
                    $userBool    = $answer !== null ? ((string) $answer === '1') : false;
                    $correctBool = (bool) (int) $question['correct_boolean'];
                    $score = $this->gradingService->gradeTrueFalse($userBool, $correctBool);
                    $userAnswerText    = $answer !== null ? ($userBool ? 'True' : 'False') : '(no answer)';
                    $correctAnswerText = $correctBool ? 'True' : 'False';
                    break;

                case 'multiple_choice':
                    $options    = $this->questionRepo->findOptionsByQuestionId((int) $question['question_id']);
                    $selectedId = $answer !== null ? (int) $answer : 0;
                    $correctId  = 0;

                    foreach ($options as $opt) {
                        if ((int) $opt['is_correct'] === 1) {
                            $correctId         = (int) $opt['option_id'];
                            $correctAnswerText = $opt['option_text'];
                            break;
                        }
                    }

                    $score = $this->gradingService->gradeMultipleChoice($selectedId, $correctId);

                    foreach ($options as $opt) {
                        if ((int) $opt['option_id'] === $selectedId) {
                            $userAnswerText = $opt['option_text'];
                            break;
                        }
                    }
                    break;

                case 'multiple_select':
                    $options     = $this->questionRepo->findOptionsByQuestionId((int) $question['question_id']);
                    $selectedIds = is_array($answer) ? array_map('intval', $answer) : [];
                    $correctIds  = [];
                    $allIds      = [];
                    $correctTexts  = [];
                    $selectedTexts = [];

                    foreach ($options as $opt) {
                        $oid    = (int) $opt['option_id'];
                        $allIds[] = $oid;
                        if ((int) $opt['is_correct'] === 1) {
                            $correctIds[]   = $oid;
                            $correctTexts[] = $opt['option_text'];
                        }
                        if (in_array($oid, $selectedIds, true)) {
                            $selectedTexts[] = $opt['option_text'];
                        }
                    }

                    $score = $this->gradingService->gradeMultipleSelect($selectedIds, $correctIds, $allIds);
                    $userAnswerText    = $selectedTexts !== [] ? implode(', ', $selectedTexts) : '(none selected)';
                    $correctAnswerText = implode(', ', $correctTexts);
                    break;

                case 'fill_blank':
                    $userAnswers     = is_array($answer) ? array_map('strval', $answer) : [];
                    $acceptedAnswers = json_decode((string) ($question['fill_blank_answers'] ?? '[]'), true) ?: [];

                    $score = $this->gradingService->gradeFillBlank(
                        $userAnswers,
                        $acceptedAnswers,
                        (bool) (int) $question['is_case_sensitive'],
                    );

                    $userAnswerText = $userAnswers !== [] ? implode(', ', $userAnswers) : '(no answer)';
                    $correctParts   = array_map(
                        static fn(array $g): string => implode(' / ', $g),
                        $acceptedAnswers,
                    );
                    $correctAnswerText = implode('; ', $correctParts);
                    break;

                case 'flashcard':
                    $selfCorrect    = $answer !== null ? ((string) $answer === '1') : false;
                    $score          = $this->gradingService->gradeFlashcard($selfCorrect);
                    $userAnswerText    = $selfCorrect ? 'Marked correct' : 'Marked incorrect';
                    $correctAnswerText = $question['flashcard_answer'] ?? '';
                    break;
            }

            $totalScore += $score;

            $results[] = [
                'question'       => $question,
                'score'          => $score,
                'user_answer'    => $userAnswerText,
                'correct_answer' => $correctAnswerText,
                'feedback'       => $score >= 1.0
                    ? ($question['feedback_correct'] ?? '')
                    : ($question['feedback_incorrect'] ?? ''),
            ];
        }

        $totalQuestions = count($questions);
        $percentage     = $totalQuestions > 0
            ? round(($totalScore / $totalQuestions) * 100, 2)
            : 0.0;

        $this->attemptRepo->create(
            $moduleId,
            $percentage,
            $totalQuestions,
            round($totalScore, 2),
        );

        echo $this->render('quiz/result.html.twig', [
            'course'          => $course,
            'module'          => $module,
            'results'         => $results,
            'total_score'     => round($totalScore, 2),
            'total_questions' => $totalQuestions,
            'percentage'      => round($percentage, 1),
        ]);
    }

    // ── Report Handler ────────────────────────────────────────────────

    private function reportIndex(): void
    {
        $attempts = $this->attemptRepo->findAllWithDetails();
        echo $this->render('report/index.html.twig', ['attempts' => $attempts]);
    }
}
