<?php

namespace DatabaseImportExport\Providers;

use App\Filament\Server\Resources\Databases\DatabaseResource;
use DatabaseImportExport\Actions\ExportDatabaseAction;
use DatabaseImportExport\Actions\ImportDatabaseAction;
use Illuminate\Support\ServiceProvider;

class DatabaseImportExportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        DatabaseResource::modifyTable(function ($table) {
            return $table->recordActions([
                ExportDatabaseAction::make(),
                ImportDatabaseAction::make(),
                
                \Filament\Actions\ViewAction::make()
                    ->modalHeading(fn (\App\Models\Database $database) => trans('server/database.viewing', ['database' => $database->database])),
                
                \Filament\Actions\DeleteAction::make()
                    ->successNotificationTitle(null)
                    ->using(function (\App\Models\Database $database, \App\Services\Databases\DatabaseManagementService $service) {
                        try {
                            $service->delete($database);

                            \Filament\Notifications\Notification::make()
                                ->title(trans('server/database.delete_notification', ['database' => $database->database]))
                                ->success()
                                ->send();
                        } catch (\Exception $exception) {
                            \Filament\Notifications\Notification::make()
                                ->title(trans('server/database.delete_notification_fail', ['database' => $database->database]))
                                ->danger()
                                ->send();

                            report($exception);
                        }
                    }),
            ]);
        });
    }

    public function boot(): void
    {
        //
    }
}
