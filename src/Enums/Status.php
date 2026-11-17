<?php

declare(strict_types=1);

namespace Snapsbrew\Enums;

/**
 * The status of a snap, of a batch, or of one batch item.
 */
enum Status: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Canceled = 'canceled';

    /**
     * True when the status cannot change again.
     */
    public function isFinished(): bool
    {
        return match ($this) {
            self::Completed, self::Failed, self::Canceled => true,
            self::Pending, self::Processing => false,
        };
    }
}
