<?php

namespace DatabaseImportExport\Services;

use App\Models\Database;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PDO;

class DatabaseImportService
{
    private array $blockedStatements;
    private array $filteredLines = [];

    public function __construct()
    {
        $this->blockedStatements = config('database-import-export.blocked_statements', [
            'CREATE DATABASE',
            'DROP DATABASE',
            'CREATE USER',
            'DROP USER',
            'GRANT',
            'REVOKE',
            'USE ',
        ]);
    }

    public function import(Database $database, string $filePath): void
    {
        $absolutePath = Storage::disk('local')->path($filePath);

        if (!file_exists($absolutePath)) {
            throw new Exception('Import file not found');
        }

        try {
            $sqlContent = file_get_contents($absolutePath);
            
            if ($sqlContent === false) {
                throw new Exception('Unable to read SQL file');
            }

            $sanitizedSql = $this->sanitizeSqlContent($sqlContent);

            $this->executeSqlStatements($database, $sanitizedSql);

            if (!empty($this->filteredLines)) {
                Log::warning('Dangerous SQL statements filtered during import', [
                    'database' => $database->database,
                    'filtered_count' => count($this->filteredLines),
                    'filtered_statements' => array_slice($this->filteredLines, 0, 10),
                ]);
            }

            Log::info('Database imported successfully', [
                'database' => $database->database,
                'file' => basename($filePath),
                'filtered' => count($this->filteredLines),
            ]);
        } catch (Exception $e) {
            Log::error('Database import failed', [
                'database' => $database->database,
                'error' => $e->getMessage(),
            ]);
            
            throw new Exception('Import failed: ' . $e->getMessage());
        } finally {
            Storage::disk('local')->delete($filePath);
        }
    }

    private function sanitizeSqlContent(string $content): string
    {
        $lines = explode("\n", $content);
        $sanitized = [];
        $this->filteredLines = [];

        foreach ($lines as $lineNumber => $line) {
            $trimmed = trim($line);

            if (empty($trimmed) || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*')) {
                $sanitized[] = $line;
                continue;
            }

            $isBlocked = false;
            foreach ($this->blockedStatements as $blockedStatement) {
                if (stripos($trimmed, $blockedStatement) === 0) {
                    $isBlocked = true;
                    $this->filteredLines[] = [
                        'line' => $lineNumber + 1,
                        'statement' => substr($trimmed, 0, 100),
                    ];
                    break;
                }
            }

            if (!$isBlocked) {
                $sanitized[] = $line;
            }
        }

        return implode("\n", $sanitized);
    }

    private function executeSqlStatements(Database $database, string $sql): void
    {
        $host = $database->host->host;
        $port = $database->host->port;
        $username = $database->username;
        $password = $database->password;
        $databaseName = $database->database;

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $databaseName
        );

        try {
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            $pdo->exec('SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO"');
            $pdo->exec('SET time_zone = "+00:00"');

            $statements = $this->splitSqlStatements($sql);

            foreach ($statements as $statement) {
                $trimmed = trim($statement);
                if (!empty($trimmed)) {
                    $pdo->exec($trimmed);
                }
            }

            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        } catch (\PDOException $e) {
            throw new Exception('SQL execution error: ' . $e->getMessage());
        }
    }

    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $inString = false;
        $stringChar = '';
        $escaped = false;
        $inDelimiter = false;
        $delimiter = ';';

        $lines = explode("\n", $sql);
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            
            if (stripos($trimmed, 'DELIMITER') === 0) {
                if (!empty($current)) {
                    $statements[] = $current;
                    $current = '';
                }
                $parts = preg_split('/\s+/', $trimmed, 2);
                $delimiter = $parts[1] ?? ';';
                continue;
            }

            $current .= $line . "\n";
            
            if (str_ends_with(rtrim($line), $delimiter)) {
                $statements[] = substr($current, 0, -strlen($delimiter) - 1);
                $current = '';
            }
        }

        if (!empty(trim($current))) {
            $statements[] = $current;
        }

        return $statements;
    }

    public function getFilteredStatements(): array
    {
        return $this->filteredLines;
    }
}
