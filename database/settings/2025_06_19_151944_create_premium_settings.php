<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('premium.open_date', 25);
    }

    public function down(): void
    {
        $this->delete('premium');
    }
};
