<?php

namespace App\Enum;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum BookingStatusEnum: string implements HasColor, HasIcon, HasLabel
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::CONFIRMED => 'Confirmed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'yellow',
            self::CONFIRMED => 'success',
            self::CANCELLED => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PENDING => 'heroicon-m-question-mark-circle',
            self::CONFIRMED => 'heroicon-m-check-circle',
            self::CANCELLED => 'heroicon-m-x-circle',
        };
    }
}
