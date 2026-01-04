<?php

declare(strict_types=1);

namespace App\Enum;

enum MatchStatus: string
{
    case NOT_STARTED = 'Not Started';
    case SCHEDULED = 'scheduled';
    case FIRST_HALF = 'First Half';
    case HALFTIME = 'Halftime';
    case SECOND_HALF = 'Second Half';
    case LIVE = 'live';
    case FINISHED = 'finished';
    case MATCH_FINISHED = 'Match Finished';
    case POSTPONED = 'postponed';
    case MATCH_POSTPONED = 'Match Postponed';
    case CANCELLED = 'cancelled';

    public function isLive(): bool
    {
        return in_array($this, [
            self::FIRST_HALF,
            self::SECOND_HALF,
            self::HALFTIME,
            self::LIVE,
        ], true);
    }

    public function isFinished(): bool
    {
        return in_array($this, [
            self::FINISHED,
            self::MATCH_FINISHED,
        ], true);
    }

    public function isScheduled(): bool
    {
        return in_array($this, [
            self::NOT_STARTED,
            self::SCHEDULED,
        ], true);
    }

    public function isCancelled(): bool
    {
        return in_array($this, [
            self::CANCELLED,
            self::POSTPONED,
            self::MATCH_POSTPONED,
        ], true);
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::NOT_STARTED => 'Pas commencé',
            self::SCHEDULED => 'Programmé',
            self::FIRST_HALF => 'Première mi-temps',
            self::HALFTIME => 'Mi-temps',
            self::SECOND_HALF => 'Deuxième mi-temps',
            self::LIVE => 'En direct',
            self::FINISHED => 'Terminé',
            self::MATCH_FINISHED => 'Match terminé',
            self::POSTPONED => 'Reporté',
            self::MATCH_POSTPONED => 'Match reporté',
            self::CANCELLED => 'Annulé',
        };
    }

    public function getBadgeClass(): string
    {
        return match (true) {
            $this->isLive() => 'badge-danger',
            $this->isFinished() => 'badge-success',
            $this->isScheduled() => 'badge-info',
            $this->isCancelled() => 'badge-warning',
            default => 'badge-secondary',
        };
    }
}
