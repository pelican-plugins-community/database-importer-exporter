<?php

namespace DatabaseImportExport\Actions;

use App\Enums\SubuserPermission;
use App\Models\Database;
use App\Models\Server;
use DatabaseImportExport\Services\DatabaseExportService;
use Exception;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconSize;

class ExportDatabaseAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'export';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Export')
            ->tooltip('Export database to SQL')
            ->icon('tabler-database-export')
            ->iconSize(IconSize::Large)
            ->iconButton()
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Export Database')
            ->modalDescription('Are you sure you want to export this database? A SQL file will be generated and downloaded.')
            ->modalSubmitActionLabel('Export')
            ->authorize(function () {
                /** @var Server $server */
                $server = Filament::getTenant();
                return user()?->can(SubuserPermission::DatabaseViewPassword, $server);
            })
            ->action(function (Database $record, DatabaseExportService $service) {
                try {
                    $filePath = $service->export($record);

                    Notification::make()
                        ->title('Database ' . $record->database . ' exported successfully')
                        ->success()
                        ->send();

                    return response()->download($filePath)->deleteFileAfterSend();
                } catch (Exception $exception) {
                    Notification::make()
                        ->title('Database export failed')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    report($exception);
                }
            });
    }
}
