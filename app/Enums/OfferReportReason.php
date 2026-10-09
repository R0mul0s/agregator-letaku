<?php

/**
 * Důvod hlášení chyby v akci (R125).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Enums;

enum OfferReportReason: string
{
    case WrongPrice = 'wrong_price';
    case WrongValidity = 'wrong_validity';
    // Akce není sleva, nebo není v obchodě vůbec (R8)
    case NotOnSale = 'not_on_sale';
    // Název, obrázek nebo balení nepatří k akci
    case WrongProduct = 'wrong_product';
    case Other = 'other';

    /**
     * Název pro zobrazení (lang/cs/app.php, skupina ui.offer_reports.reasons).
     */
    public function label(): string
    {
        return __('app.ui.offer_reports.reasons.'.$this->value);
    }
}
