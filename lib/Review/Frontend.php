<?php

declare(strict_types=1);

namespace FriendsOfREDAXO\IssueTracker\Review;

use rex;
use rex_addon;
use rex_article;
use rex_clang;
use rex_extension;
use rex_extension_point;
use rex_i18n;
use rex_request;
use rex_url;

/**
 * Review-Leiste im Frontend.
 *
 *  - Link mit Schlüssel (?it_review=…) einlösen (Reviewer::redeem)
 *  - SLICE_SHOW: unsichtbare Marker <!--it-slice:ID--> … <!--/it-slice--> um jeden Slice – nur für Reviewer;
 *    der Inhalts-Cache bekommt dafür eine Abfrage, keine festen Kommentare
 *  - OUTPUT_FILTER: Web Component <it-review-bar> + Konfiguration (JSON) + Skript vor </body>
 *
 * @package issue_tracker
 */
final class Frontend
{
    private static ?bool $active = null;

    public static function boot(): void
    {
        $token = rex_request::get(Links::PARAM, 'string', '');
        if ('' !== $token) {
            Reviewer::redeem($token);
        }

        rex_extension::register('SLICE_SHOW', [self::class, 'markSlice'], rex_extension::LATE);

        rex_extension::register('PACKAGES_INCLUDED', static function (): void {
            if (!self::active()) {
                return;
            }
            // Seiten mit Leiste nie zwischenspeichern oder indexieren
            header('Cache-Control: no-store, private');
            header('X-Robots-Tag: noindex');
            rex_extension::register('OUTPUT_FILTER', [self::class, 'inject'], rex_extension::LATE);
        }, rex_extension::LATE);
    }

    /** Ist für diesen Aufruf ein Reviewer angemeldet? (auch aus dem Inhalts-Cache abgefragt) */
    public static function active(): bool
    {
        if (null === self::$active) {
            self::$active = rex::isFrontend() && !rex_request::isXmlHttpRequest() && null !== Reviewer::current();
        }
        return self::$active;
    }

    /** @param rex_extension_point<string> $ep */
    public static function markSlice(rex_extension_point $ep): string
    {
        $content = (string) $ep->getSubject();
        $sliceId = (int) $ep->getParam('slice_id');
        if ($sliceId <= 0 || rex::isBackend()) {
            return $content;
        }
        $open = '<!--it-slice:' . $sliceId . '-->';
        $close = '<!--/it-slice-->';
        // Inhalts-Cache: der Slice ist PHP-Code (require rex_stream…) – Marker nur ausgeben, wenn ein Reviewer da ist
        if (str_contains($content, 'rex_stream::factory(')) {
            $check = "if (class_exists('" . self::class . "') && \\" . self::class . "::active()) { echo '%s'; }\n";
            return sprintf($check, $open) . $content . sprintf($check, $close);
        }
        return self::active() ? $open . $content . $close : $content;
    }

    /** @param rex_extension_point<string> $ep */
    public static function inject(rex_extension_point $ep): ?string
    {
        $html = (string) $ep->getSubject();
        $pos = strripos($html, '</body>');
        $reviewer = Reviewer::current();
        if (false === $pos || null === $reviewer || !str_contains(strtolower(substr($html, 0, 600)), '<html')) {
            return null;
        }
        $article = rex_article::getCurrent();
        $rounds = array_values(array_filter($reviewer->rounds, static fn (Round $r): bool => $r->coversArticle($article?->getId())));
        if ([] === $rounds) {
            return null;
        }
        $addon = rex_addon::get('issue_tracker');
        $config = self::config($reviewer, $rounds, $article);
        $markup = "\n<it-review-bar></it-review-bar>"
            . '<script type="application/json" id="it-review-config">' . json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>'
            . '<script src="' . rex_escape(self::asset('js/it-review.js')) . '" defer></script>' . "\n";
        return substr($html, 0, $pos) . $markup . substr($html, $pos);
    }

    /**
     * @param list<Round> $rounds
     * @return array<string, mixed>
     */
    private static function config(Reviewer $reviewer, array $rounds, ?rex_article $article): array
    {
        $addon = rex_addon::get('issue_tracker');
        $lang = str_starts_with((string) rex_clang::getCurrent()->getCode(), 'de') ? 'de' : 'en';
        $msg = static function (string $key) use ($lang): string {
            $i18n = rex_i18n::getLocale();
            rex_i18n::setLocale('de' === $lang ? 'de_de' : 'en_gb', false);
            $text = rex_i18n::msg('issue_tracker_review_js_' . $key);
            rex_i18n::setLocale($i18n, false);
            return $text;
        };
        $strings = [];
        foreach (self::JS_STRINGS as $key) {
            $strings[$key] = $msg($key);
        }
        return [
            'api' => self::abs(rex_url::frontendController(['rex-api-call' => 'issue_tracker_review'], false)),
            'css' => self::asset('css/it-review.css'),
            'capture' => self::asset('vendor/modern-screenshot.js'),
            'lang' => $lang,
            'me' => ['name' => $reviewer->name(), 'guest' => $reviewer->isGuest(), 'agency' => $reviewer->isAgency()],
            'rounds' => array_map(static fn (Round $r): array => ['id' => $r->id, 'title' => $r->title], $rounds),
            'page' => [
                'article_id' => $article?->getId() ?? 0,
                'clang_id' => rex_clang::getCurrentId(),
            ],
            'kinds' => array_combine(ReviewService::KINDS, array_map(static fn (string $k): string => $msg('kind_' . $k), ReviewService::KINDS)),
            'maxText' => ReviewService::MAX_TEXT,
            't' => $strings,
        ];
    }

    /** Adresse einer Datei des AddOns mit Änderungszeit als Version (neue Fassung kommt sofort an, kein alter Cache) */
    private static function asset(string $file): string
    {
        $addon = rex_addon::get('issue_tracker');
        return self::abs($addon->getAssetsUrl($file)) . '?v=' . (int) @filemtime($addon->getAssetsPath($file));
    }

    /** Relative Frontend-Adressen („./assets/…“, „index.php?…“) gelten sonst relativ zur aktuellen Seite */
    private static function abs(string $url): string
    {
        if (preg_match('#^(/|https?:)#', $url)) {
            return $url;
        }
        $base = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/') . '/';
        return $base . (string) preg_replace('#^(\./)+#', '', $url);
    }

    private const JS_STRINGS = [
        'bar_label', 'comment_page', 'pick', 'pick_hint', 'pick_cancel', 'list', 'list_all', 'list_page', 'empty', 'empty_all',
        'new_title', 'element', 'page_only', 'kind', 'text', 'text_hint', 'screenshot', 'send', 'cancel', 'close',
        'reply', 'reply_send', 'sent', 'error', 'show', 'changed', 'gone', 'done', 'agency', 'you', 'move', 'dock_top',
        'dock_bottom', 'hide', 'show_bar', 'round', 'end', 'end_confirm_guest', 'end_confirm_user', 'open_backend', 'section', 'markers', 'capturing', 'other_page',
    ];
}
