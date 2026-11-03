<?php
declare(strict_types=1);

namespace Prospak\Core;

final class Clock
{
    public static function display(string $utcDateTime): string
    {
        return (new \DateTimeImmutable($utcDateTime, new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone('Africa/Lagos'))
            ->format('d M Y, g:i A T');
    }
}
