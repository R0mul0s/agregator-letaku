<?php

/**
 * Kanál RSS akcí obchodu nebo produktu katalogu (R149): `/akce/lidl/rss`, `/akce/pivo/rss`.
 * Čtečky se ptají často — odpověď nese `Last-Modified` poslední změny akcí a na podmíněný
 * dotaz vrátí 304; do výsledků hledání nepatří (`X-Robots-Tag`), obsah je na stránce výpisu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Offers\OfferFeed;
use App\Domain\Offers\OfferPages;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OfferFeedController extends Controller
{
    /** Typ odpovědi kanálu. */
    private const CONTENT_TYPE = 'application/rss+xml; charset=utf-8';

    /**
     * Kanál pro část adresy; neznámý obchod nebo produkt je 404.
     */
    public function __invoke(Request $request, string $slug, OfferPages $pages, OfferFeed $feed): Response
    {
        $target = $pages->resolve($slug);
        abort_if($target === null, Response::HTTP_NOT_FOUND);

        $channel = $feed->build($target['chain'], $target['productId']);
        $response = response()
            ->view('feeds.offers', ['channel' => $channel])
            ->header('Content-Type', self::CONTENT_TYPE)
            ->header('X-Robots-Tag', 'noindex')
            ->setPublic()
            ->setMaxAge(config()->integer('letaky.feed.cache_seconds'));
        if ($channel['updatedAt'] !== null) {
            $response->setLastModified($channel['updatedAt']);
        }
        $response->isNotModified($request);

        return $response;
    }
}
