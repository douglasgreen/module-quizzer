<?php

declare(strict_types=1);

namespace DouglasGreen\ModuleQuizzer\Config;

use PDO;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads database connection parameters from a YAML file and creates PDO instances.
 */
final readonly class DatabaseConfig
{
    public function __construct(
        private string $host,
        private int $port,
        private string $database,
        private string $username,
        private string $password,
        private string $charset,
    ) {
    }

    public static function load(string $path): self
    {
        if (! file_exists($path)) {
            throw new RuntimeException('Database config not found: ' . $path);
        }

        /** @var array{database: array{host: string, port: int, name: string, username: string, password: string, charset?: string}} $config */
        $config = Yaml::parseFile($path);
        $db = $config['database'];

        return new self(
            host: $db['host'],
            port: (int) $db['port'],
            database: $db['name'],
            username: $db['username'],
            password: $db['password'],
            charset: $db['charset'] ?? 'utf8mb4',
        );
    }

    public function createPdo(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->host,
            $this->port,
            $this->database,
            $this->charset,
        );

        return new PDO($dsn, $this->username, $this->password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
}
