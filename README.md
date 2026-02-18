# ModuleQuizzer

A self-hosted, single-user e-learning application for building courses
with module-based lessons and auto-graded quizzes.

## Features

- **Courses → Modules → Lessons + Questions** hierarchy
- **Five question types**: True/False, Multiple Choice, Multiple Select,
  Fill-in-Blank (one or more blanks), Flashcard (self-graded)
- **Web CRUD** for all content (Bootstrap 5.3 UI)
- **CLI tools** for batch XML import/export
- **Score report** showing dates and percentages per module
- Partial-credit grading for multiple-select and multi-blank questions
- Content stored as XML documents (one per lesson/question) for portability

## Requirements

- PHP 8.3+
- MySQL 8.0+ (or MariaDB 10.6+)
- Composer 2.6+
- Apache with `mod_rewrite` (or Nginx equivalent)
- `ext-pdo`, `ext-dom`, `ext-mbstring`
- (optional) `ext-intl` for Unicode NFKC normalisation

## Installation

1. Clone and install dependencies:
   ```bash
   git clone <repo-url> module-quizzer && cd module-quizzer
   composer install
   ```

2. Create the database:
   ```bash
   mysql -u root -p < sql/schema.sql
   ```

3. Copy and edit the config:
   ```bash
   cp config/database.yaml config/database.yaml
   # Edit host, name, username, password
   ```

4. Point your web server document root to `public/`.
   Ensure `.htaccess` rewrites are enabled (Apache) or configure
   Nginx to route all requests to `public/index.php`.

5. Open `http://localhost/` in your browser.

## CLI Usage

```bash
# List courses
php bin/cli.php list-courses

# List modules in a course
php bin/cli.php list-modules 1

# Export a course to XML
php bin/cli.php export-course 1 ./export/my-course

# Import a course from XML
php bin/cli.php import-course ./content/sample

# Delete a course
php bin/cli.php delete-course 1
```

## XML Format

Each export contains a directory with:

```
course.xml           — course title and description
module-1/
  module.xml         — module title and sort order
  lesson.xml         — lesson HTML wrapped in CDATA
  question-001.xml   — individual question document
  question-002.xml
module-2/
  ...
```

See `content/sample/` for a working example.

## Grading Rules

| Type             | Scoring                                              |
|-----------------|------------------------------------------------------|
| True/False       | 1 point if correct, 0 otherwise                     |
| Multiple Choice  | 1 point if correct option selected, 0 otherwise     |
| Multiple Select  | `max(0, c/C − 0.5·w/W)` partial credit              |
| Fill-in-Blank    | `correct_blanks / total_blanks` partial credit       |
| Flashcard        | 1 point if self-marked correct, 0 otherwise          |

All questions count equally (max 1 point each).
Final score = `(sum of points / question count) × 100%`.

## Quality Assurance

```bash
composer cs:check   # PSR-12 code style
composer stan       # PHPStan level 8
composer test       # PHPUnit
composer qa         # All of the above
```
