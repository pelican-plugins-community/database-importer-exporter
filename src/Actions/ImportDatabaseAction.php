<?php

namespace DatabaseImportExport\Actions;

use App\Enums\SubuserPermission;
use App\Models\Database;
use App\Models\Server;
use DatabaseImportExport\Services\DatabaseImportService;
use Exception;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconSize;

class ImportDatabaseAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'import';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Import')
            ->tooltip('Import SQL file to database')
            ->icon('tabler-database-import')
            ->iconSize(IconSize::Large)
            ->iconButton()
            ->color('primary')
            ->modalHeading('Import to Database')
            ->modalDescription('Warning: Importing will replace existing data if tables have the same name. This action is irreversible.')
            ->modalSubmitActionLabel('Import')
            ->authorize(function () {
                /** @var Server $server */
                $server = Filament::getTenant();
                return user()?->can(SubuserPermission::DatabaseUpdate, $server);
            })
            ->form([
                FileUpload::make('sql_file')
                    ->label('SQL File')
                    ->acceptedFileTypes(['application/sql', 'text/plain', 'text/x-sql', 'application/x-sql'])
                    ->maxSize(config('database-import-export.max_upload_size', 10240))
                    ->disk('local')
                    ->directory('temp-imports')
                    ->preserveFilenames()
                    ->required()
                    ->helperText('Max size: ' . (config('database-import-export.max_upload_size', 10240) / 1024) . ' MB. Accepted formats: .sql'),
            ])
            ->action(function (array $data, Database $record, DatabaseImportService $service) {
                try {
                    $service->import($record, $data['sql_file']);

                    $filteredStatements = $service->getFilteredStatements();

                    if (!empty($filteredStatements)) {
                        Notification::make()
                            ->title('Database imported with warnings')
                            ->body(count($filteredStatements) . ' dangerous statement(s) were filtered (CREATE DATABASE, CREATE USER, GRANT, etc.)')
                            ->warning()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Database ' . $record->database . ' imported successfully')
                            ->success()
                            ->send();
                    }
                } catch (Exception $exception) {
                    Notification::make()
                        ->title('Database import failed')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    report($exception);
                }
            });
    }
}
