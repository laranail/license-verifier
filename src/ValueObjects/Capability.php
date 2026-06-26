<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\ValueObjects;

/**
 * Capability keys a driver may declare via Driver::capabilities().
 */
enum Capability: string
{
    case OfflineTokens = 'offline_tokens';
    case Refresh = 'refresh';
    case Heartbeat = 'heartbeat';
    case Entitlements = 'entitlements';
    case Seats = 'seats';
    case SeatManagement = 'seat_management';
    case Domain = 'domain';
}
