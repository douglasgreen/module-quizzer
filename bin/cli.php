#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use DouglasGreen\ModuleQuizzer\Config\DatabaseConfig;
use DouglasGreen\ModuleQuizzer\Repository\AttemptRepository;
use DouglasGreen\ModuleQuizzer\Repository\CourseRepository;
use DouglasGreen\ModuleQuizzer\Repository\LessonRepository;
use DouglasGreen\ModuleQuizzer\Repository\ModuleRepository;
use DouglasGreen\ModuleQuizzer\Repository\QuestionRepository;
use DouglasGreen\ModuleQuizzer\Service\XmlExporter;
use DouglasGreen\ModuleQuizzer\Service\XmlImporter;

$config = DatabaseConfig::load(__DIR__ . '/../config/database.yaml');
$pdo    = $config->createPdo();

$courseRepo   = new CourseRepository($pdo);
$moduleRepo   = new ModuleRepository($pdo);
$lessonRepo   = new LessonRepository($pdo);
$questionRepo = new QuestionRepository($pdo);
$attemptRepo  = new AttemptRepository($pdo);

$exporter = new XmlExporter($courseRepo, $moduleRepo, $lessonRepo, $questionRepo);
$importer = new XmlImporter($courseRepo, $moduleRepo, $lessonRepo, $questionRepo);

$command = $argv[1] ?? '';

switch ($command) {
    case 'list-courses':
        $courses = $courseRepo->findAll();
        if ($courses === []) {
            echo "No courses found.\n";
            break;
        }
        echo str_pad('ID', 6) . str_pad('Title', 50) . "Created\n";
        echo str_repeat('─', 80) . "\n";
        foreach ($courses as $c) {
            echo str_pad((string) $c['course_id'], 6)
               . str_pad($c['title'], 50)
               . $c['created_at'] . "\n";
        }
        break;

    case 'list-modules':
        $courseId = (int) ($argv[2] ?? 0);
        if ($courseId <= 0) {
            fwrite(STDERR, "Usage: php cli.php list-modules <course_id>\n");
            exit(1);
        }
        $modules = $moduleRepo->findByCourseId($courseId);
        if ($modules === []) {
            echo "No modules found for course {$courseId}.\n";
            break;
        }
        echo str_pad('ID', 6) . str_pad('Title', 50) . "Order\n";
        echo str_repeat('─', 60) . "\n";
        foreach ($modules as $m) {
            echo str_pad((string) $m['module_id'], 6)
               . str_pad($m['title'], 50)
               . $m['sort_order'] . "\n";
        }
        break;

    case 'export-course':
        $courseId  = (int) ($argv[2] ?? 0);
        $outputDir = $argv[3] ?? '';
        if ($courseId <= 0 || $outputDir === '') {
            fwrite(STDERR, "Usage: php cli.php export-course <course_id> <output_dir>\n");
            exit(1);
        }
        try {
            $exporter->exportCourse($courseId, $outputDir);
            echo "Course {$courseId} exported to {$outputDir}/\n";
        } catch (RuntimeException $e) {
            fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
            exit(1);
        }
        break;

    case 'import-course':
        $inputDir = $argv[2] ?? '';
        if ($inputDir === '') {
            fwrite(STDERR, "Usage: php cli.php import-course <input_dir>\n");
            exit(1);
        }
        try {
            $courseId = $importer->importCourse($inputDir);
            echo "Course imported successfully with ID: {$courseId}\n";
        } catch (RuntimeException $e) {
            fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
            exit(1);
        }
        break;

    case 'delete-course':
        $courseId = (int) ($argv[2] ?? 0);
        if ($courseId <= 0) {
            fwrite(STDERR, "Usage: php cli.php delete-course <course_id>\n");
            exit(1);
        }
        $courseRepo->delete($courseId);
        echo "Course {$courseId} deleted.\n";
        break;

    default:
        echo <<<HELP
        ModuleQuizzer CLI

        Commands:
          list-courses                          List all courses
          list-modules  <course_id>             List modules in a course
          export-course <course_id> <dir>       Export course to XML directory
          import-course <dir>                   Import course from XML directory
          delete-course <course_id>             Delete a course and all its data

        HELP;
        break;
}
