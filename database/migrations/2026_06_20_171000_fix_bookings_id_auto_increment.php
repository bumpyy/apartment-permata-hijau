<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->isMysql()) {
            return;
        }

        DB::statement('ALTER TABLE bookings ENGINE = InnoDB');

        if ($this->idColumnIsAutoIncrement() && $this->hasPrimaryKey()) {
            return;
        }

        if ($this->hasPrimaryKey()) {
            DB::statement('ALTER TABLE bookings MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');

            return;
        }

        DB::statement('ALTER TABLE bookings MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY');
    }

    public function down(): void
    {
        if (! $this->isMysql()) {
            return;
        }

        DB::statement('ALTER TABLE bookings MODIFY id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE bookings DROP PRIMARY KEY');
    }

    private function isMysql(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function hasPrimaryKey(): bool
    {
        $primaryKey = DB::selectOne(
            "SELECT COUNT(*) as count
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
                AND TABLE_NAME = 'bookings'
                AND CONSTRAINT_TYPE = 'PRIMARY KEY'"
        );

        return (int) $primaryKey->count > 0;
    }

    private function idColumnIsAutoIncrement(): bool
    {
        $idColumn = DB::selectOne(
            "SELECT EXTRA
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'bookings'
                AND COLUMN_NAME = 'id'"
        );

        return str_contains((string) $idColumn->EXTRA, 'auto_increment');
    }
};
