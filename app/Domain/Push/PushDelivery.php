<?php

/**
 * Jak dopadlo odeslání upozornění na jedno zařízení (R66).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Domain\Push;

enum PushDelivery
{
    case Sent;
    // Push služba odběr nezná (404 / 410) — uživatel upozornění v prohlížeči zakázal nebo ho odinstaloval
    case Expired;
    case Failed;
}
