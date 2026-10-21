<?php

namespace FriendsOfREDAXO\IssueTracker;

use rex;
use rex_be_controller;
use rex_be_page;
use rex_config;
use rex_i18n;
use rex_url;

/**
 * Vollbild-App des Issue Trackers: eigener Rahmen (Kopfleiste, Bereiche, Schließen) über dem REDAXO-Backend.
 *
 * Die Seiten des Issue Trackers werden darin ausgegeben; geöffnet wird sie als Ebene über der aktuellen
 * Backend-Seite (Menüpunkt, Knopf in der Kopfzeile, siehe assets/js/it-launcher.js) oder direkt per Link.
 */
final class AppShell
{
    /** Vollbild-Darstellung eingeschaltet (Einstellung „Als Vollbild öffnen“, Standard: an) */
    public static function isEnabled(): bool
    {
        return (bool) rex_config::get('issue_tracker', 'fullscreen', true);
    }

    /** Darf der aktuelle Benutzer den Issue Tracker öffnen? */
    public static function userCanOpen(): bool
    {
        $user = rex::getUser();
        if (!$user) {
            return false;
        }
        $page = rex_be_controller::getPageObject('issue_tracker');
        return $page instanceof rex_be_page && $page->checkPermission($user);
    }

    /**
     * Anfang des App-Rahmens (Kopfleiste + Inhaltsbereich).
     */
    public static function open(): string
    {
        $user = rex::requireUser();
        $main = rex_be_controller::getPageObject('issue_tracker');
        $current = rex_be_controller::getCurrentPageObject();
        $part2 = (string) rex_be_controller::getCurrentPagePart(2);

        $tabs = '';
        $subnav = '';
        $sectionTitle = '';
        if ($main instanceof rex_be_page) {
            foreach ($main->getSubpages() as $key => $sub) {
                if ($sub->isHidden() || !$sub->checkPermission($user)) {
                    continue;
                }
                $active = $key === $part2 || ('issues' === $key && in_array($part2, ['dashboard', 'knowledge', 'projects', 'messages', 'preferences'], true));
                if ($active) {
                    $sectionTitle = (string) $sub->getTitle();
                }
                $badge = '';
                if ('issues' === $key) {
                    $unread = Message::getUnreadCount($user->getId());
                    if ($unread > 0) {
                        $badge = ' <span class="it-app-badge" aria-label="' . rex_escape(rex_i18n::msg('issue_tracker_app_unread', $unread)) . '">' . (int) $unread . '</span>';
                    }
                }
                $icon = (string) $sub->getIcon();
                $tabs .= '<a class="it-app-tab' . ($active ? ' is-active' : '') . '" href="' . rex_escape((string) $sub->getHref()) . '" title="' . rex_escape((string) $sub->getTitle()) . '"' . ($active ? ' aria-current="page"' : '') . '>'
                    . ('' !== $icon ? '<i class="' . rex_escape($icon) . '" aria-hidden="true"></i> ' : '')
                    . '<span class="it-app-tab-label">' . rex_escape((string) $sub->getTitle()) . '</span>' . $badge . '</a>';

                // Dritte Ebene (z. B. Einstellungen: Allgemein, E-Mail-Vorlagen, Backup)
                if ($active) {
                    $items = '';
                    $count = 0;
                    foreach ($sub->getSubpages() as $sub3) {
                        if ($sub3->isHidden() || !$sub3->checkPermission($user)) {
                            continue;
                        }
                        ++$count;
                        $on = $sub3->isActive();
                        $items .= '<a class="it-app-subtab' . ($on ? ' is-active' : '') . '" href="' . rex_escape((string) $sub3->getHref()) . '"' . ($on ? ' aria-current="page"' : '') . '>' . rex_escape((string) $sub3->getTitle()) . '</a>';
                    }
                    if ($count > 1) {
                        $subnav = '<nav class="it-app-subnav" aria-label="' . rex_escape(rex_i18n::msg('issue_tracker_app_subnav', (string) $sub->getTitle())) . '">' . $items . '</nav>';
                    }
                }
            }
        }

        $crumb = '';
        if ($current instanceof rex_be_page && $current->isHidden() && '' !== (string) $current->getTitle() && (string) $current->getTitle() !== $sectionTitle) {
            $crumb = '<span class="it-app-crumb"><i class="rex-icon fa-angle-right" aria-hidden="true"></i> ' . rex_escape((string) $current->getTitle()) . '</span>';
        }

        $title = rex_i18n::msg('issue_tracker_app_title');
        $closeLabel = rex_i18n::msg('issue_tracker_app_close');

        return '<div class="it-app" id="it-app" role="dialog" aria-modal="true" aria-labelledby="it-app-title" tabindex="-1" data-it-app>'
            . '<header class="it-app-bar">'
            . '<div class="it-app-brand"><span class="it-app-icon" aria-hidden="true"><i class="rex-icon fa-tasks"></i></span>'
            . '<h1 class="it-app-title" id="it-app-title">' . rex_escape($title) . '</h1>' . $crumb . '</div>'
            . ('' !== $tabs ? '<nav class="it-app-nav" aria-label="' . rex_escape(rex_i18n::msg('issue_tracker_app_nav')) . '">' . $tabs . '</nav>' : '')
            . '<button type="button" class="it-app-max" data-it-app-max hidden'
            . ' data-label-on="' . rex_escape(rex_i18n::msg('issue_tracker_app_fullscreen')) . '" data-label-off="' . rex_escape(rex_i18n::msg('issue_tracker_app_window')) . '"'
            . ' title="' . rex_escape(rex_i18n::msg('issue_tracker_app_fullscreen')) . '" aria-label="' . rex_escape(rex_i18n::msg('issue_tracker_app_fullscreen')) . '">'
            . '<i class="rex-icon fa-expand" aria-hidden="true"></i></button>'
            . '<a class="it-app-close" href="' . rex_escape(rex_url::backendController()) . '" data-it-app-close title="' . rex_escape(rex_i18n::msg('issue_tracker_app_close_hint')) . '">'
            . '<i class="rex-icon fa-times" aria-hidden="true"></i><span class="it-app-close-label">' . rex_escape($closeLabel) . '</span></a>'
            . '</header>'
            . $subnav
            . '<div class="it-app-body" id="it-app-body">';
    }

    public static function close(): string
    {
        return '</div></div>';
    }

    /**
     * Prüft eine Backend-Adresse dieser Installation und gibt sie bereinigt (relativ, „index.php?page=…“) zurück.
     * Nur Backend-Seiten (Parameter „page“), keine API-Aufrufe, kein Abmelden, keine CSRF-Token.
     */
    public static function normalizeBackendUrl(string $url): ?string
    {
        $url = trim($url);
        if ('' === $url || mb_strlen($url) > 1000 || preg_match('/[\s<>"]/', $url)) {
            return null;
        }
        $parts = parse_url($url);
        if (false === $parts) {
            return null;
        }
        if (isset($parts['scheme']) || isset($parts['host'])) {
            $scheme = strtolower((string) ($parts['scheme'] ?? ''));
            if (!in_array($scheme, ['http', 'https'], true) || !isset($parts['host'])) {
                return null;
            }
            $host = strtolower($parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');
            $allowed = [strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''))];
            $server = parse_url((string) rex::getServer());
            if (is_array($server) && isset($server['host'])) {
                $allowed[] = strtolower($server['host']) . (isset($server['port']) ? ':' . $server['port'] : '');
            }
            if (!in_array($host, array_filter($allowed), true)) {
                return null;
            }
            // Pfad muss der Backend-Controller sein (z. B. /redaxo/index.php)
            $backendPath = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/redaxo/index.php'))), '/') . '/';
            $path = (string) ($parts['path'] ?? '');
            if ($path !== $backendPath && $path !== $backendPath . 'index.php') {
                return null;
            }
        } elseif (isset($parts['path']) && !in_array($parts['path'], ['index.php', './index.php', ''], true)) {
            return null;
        }
        parse_str((string) ($parts['query'] ?? ''), $query);
        $page = isset($query['page']) && is_string($query['page']) ? $query['page'] : '';
        if (!preg_match('#^[a-z0-9_.\-]+(/[a-z0-9_.\-]+)*$#i', $page) || str_starts_with($page, 'issue_tracker')) {
            return null;
        }
        unset($query['_csrf_token'], $query['rex-api-call'], $query['rex_logout'], $query['_pjax']);
        foreach (array_keys($query) as $k) {
            if (!is_string($k) || !preg_match('/^[a-z0-9_\-\[\]]+$/i', $k)) {
                unset($query[$k]);
            }
        }
        $result = 'index.php?' . str_replace('%2F', '/', http_build_query($query, '', '&'));
        return mb_strlen($result) <= 500 ? $result : null;
    }

    /**
     * Artikel aus einer Backend-Adresse (Inhalt bearbeiten), falls vorhanden: [article_id, clang_id].
     *
     * @return array{0:int,1:int}|null
     */
    public static function articleFromBackendUrl(string $url): ?array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $page = (string) ($query['page'] ?? '');
        $articleId = (int) ($query['article_id'] ?? 0);
        if (!str_starts_with($page, 'content/') || $articleId <= 0 || !class_exists('rex_article')) {
            return null;
        }
        $clang = (int) ($query['clang'] ?? 0);
        if (!\rex_clang::exists($clang)) {
            $clang = \rex_clang::getStartId();
        }
        return \rex_article::get($articleId, $clang) ? [$articleId, $clang] : null;
    }

    /**
     * Anzeigename der meldenden Seite: bei Artikeln „Bereich: Artikelname“, sonst der mitgegebene Seitentitel.
     */
    public static function sourceLabel(string $url, string $title = ''): string
    {
        if ('' === trim($title) || self::articleFromBackendUrl($url)) {
            return self::backendUrlLabel($url);
        }
        return $title;
    }

    /**
     * Lesbarer Name einer Backend-Adresse, wenn kein Seitentitel mitgegeben wurde.
     */
    public static function backendUrlLabel(string $url): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $page = (string) ($query['page'] ?? '');
        $label = $page;
        $obj = '' !== $page ? rex_be_controller::getPageObject($page) : null;
        if ($obj instanceof rex_be_page && '' !== (string) $obj->getTitle()) {
            $label = (string) $obj->getTitle();
        }
        $article = self::articleFromBackendUrl($url);
        if ($article) {
            $art = \rex_article::get($article[0], $article[1]);
            if ($art) {
                $label .= ': ' . $art->getName();
            }
        }
        return $label;
    }
    /**
     * Knöpfe in der Kopfzeile des Backends (META_NAVI): Issue Tracker öffnen und neues Thema zu dieser Seite melden.
     */
    public static function launcherItems(): string
    {
        $source = self::normalizeBackendUrl('index.php?' . http_build_query($_GET, '', '&'));
        $listUrl = rex_url::backendPage('issue_tracker/issues/list');
        $newUrl = rex_url::backendPage('issue_tracker/issues/create', null !== $source ? ['it_src' => $source] : []);
        $labels = [
            'title' => rex_i18n::msg('issue_tracker_app_title'),
            'close' => rex_i18n::msg('issue_tracker_app_close'),
            'loading' => rex_i18n::msg('issue_tracker_app_loading'),
        ];
        $open = rex_i18n::msg('issue_tracker_app_open');
        $new = rex_i18n::msg('issue_tracker_app_new');
        return '<li class="it-launch-item"><a class="it-launch" href="' . $listUrl . '" data-it-app-open data-it-labels="' . rex_escape((string) json_encode($labels)) . '" title="' . rex_escape($open) . '" data-pjax="false">'
            . '<i class="rex-icon fa-tasks" aria-hidden="true"></i><span class="it-launch-label">' . rex_escape($labels['title']) . '</span></a></li>'
            . '<li class="it-launch-item"><a class="it-launch it-launch--new" href="' . $newUrl . '" data-it-app-new title="' . rex_escape(rex_i18n::msg('issue_tracker_app_new_hint')) . '" data-pjax="false">'
            . '<i class="rex-icon fa-plus-circle" aria-hidden="true"></i><span class="it-launch-label">' . rex_escape($new) . '</span></a></li>';
    }
}
