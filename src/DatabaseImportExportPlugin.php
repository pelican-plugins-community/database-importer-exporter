<?php

namespace DatabaseImportExport;

use Filament\Contracts\Plugin;
use Filament\Panel;

class DatabaseImportExportPlugin implements Plugin
{
    public function getId(): string
    {
        return 'Database-Import-Export';
    }

    public function register(Panel $panel): void
    {
        //
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
