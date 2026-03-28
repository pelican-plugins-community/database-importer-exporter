<?php

namespace DatabaseImportExport\Services;

use App\Models\Database;
use Exception;
use Ifsnop\Mysqldump\Mysqldump;
use Illuminate\Support\Facades\Log;

class DatabaseExportService
{
    public function export(Database $database): string
    {
        $host = $database->host->host;
        $port = $database->host->port;
        $username = $database->username;
        $password = $database->password;
        $databaseName = $database->database;

        $filename = 'database_export_' . $databaseName . '_' . now()->format('Y-m-d_H-i-s') . '.sql';
        $filepath = storage_path('app/temp-exports/' . $filename);

        if (!file_exists(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s',
                $host,
                $port,
                $databaseName
            );

            $dumpSettings = config('database-import-export.export_settings', [
                'no-create-db' => true,
                'add-drop-table' => true,
                'skip-definer' => true,
                'single-transaction' => true,
                'default-character-set' => 'utf8mb4',
            ]);

            $dump = new Mysqldump($dsn, $username, $password, $dumpSettings);
            
            $dump->start($filepath);

            if (!file_exists($filepath) || filesize($filepath) === 0) {
                throw new Exception('Export file is empty or was not created');
            }

            Log::info('Database exported successfully', [
                'database' => $databaseName,
                'file' => $filename,
                'size' => filesize($filepath),
            ]);

            return $filepath;
        } catch (Exception $e) {
            Log::error('Database export failed', [
                'database' => $databaseName,
                'error' => $e->getMessage(),
            ]);
            
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            
            throw new Exception('Export failed: ' . $e->getMessage());
        }
    }
}
