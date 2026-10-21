<?php

/**
 * Schnelles Tagging aus der Themenliste und dem Formular (JSON).
 *
 * Aktionen:
 * - toggle: Tag an einem Thema setzen/entfernen (issue_id, tag_id, on=1|0) – nur wer das Thema bearbeiten darf
 * - create: neues Tag anlegen (name) und optional gleich setzen (issue_id) – nur wer Tags verwalten darf
 *
 * Aufruf nur im Backend mit CSRF-Token (getUrlParams()).
 *
 * @package issue_tracker
 */

use FriendsOfREDAXO\IssueTracker\Issue;
use FriendsOfREDAXO\IssueTracker\PermissionService;
use FriendsOfREDAXO\IssueTracker\Tag;

class rex_api_issue_tracker_tags extends rex_api_function
{
    protected $published = false;

    /** Farben für neue Tags (reihum) */
    private const COLORS = ['#2a6aa8', '#23843f', '#c76a10', '#7f4ccc', '#13807f', '#b42318', '#4f4dc9', '#5f6d7c'];

    protected function requiresCsrfProtection()
    {
        return true;
    }

    public function execute()
    {
        rex_response::cleanOutputBuffers();
        $package = rex_addon::get('issue_tracker');

        $user = rex::getUser();
        if (!$user || (!$user->isAdmin() && !$user->hasPerm('issue_tracker[]') && !$user->hasPerm('issue_tracker[issuer]') && !$user->hasPerm('issue_tracker[issue_manager]'))) {
            $this->fail($package->i18n('issue_tracker_no_permission'), rex_response::HTTP_FORBIDDEN);
        }
        if ('post' !== rex_request::requestMethod()) {
            $this->fail('POST required', '405 Method Not Allowed');
        }

        $action = rex_post('action', 'string', '');
        $issueId = rex_post('issue_id', 'int', 0);
        $issue = null;
        if ($issueId > 0) {
            $issue = Issue::get($issueId);
            if (!$issue || !PermissionService::canView($issue) || !PermissionService::canEdit($issue)) {
                $this->fail($package->i18n('issue_tracker_no_permission'), rex_response::HTTP_FORBIDDEN);
            }
        }

        if ('toggle' === $action) {
            $tag = Tag::get(rex_post('tag_id', 'int', 0));
            if (!$issue || !$tag) {
                $this->fail($package->i18n('issue_tracker_quicktag_error'));
            }
            if (rex_post('on', 'int', 0) === 1) {
                $issue->addTag($tag->getId());
            } else {
                $issue->removeTag($tag->getId());
            }
            $this->send(['success' => true, 'tags' => self::tagsOf($issueId)]);
        }

        if ('create' === $action) {
            if (!PermissionService::canManageTags()) {
                $this->fail($package->i18n('issue_tracker_no_permission'), rex_response::HTTP_FORBIDDEN);
            }
            $name = trim(rex_post('name', 'string', ''));
            $name = (string) preg_replace('/\s+/u', ' ', $name);
            if ('' === $name || mb_strlen($name) > 100) {
                $this->fail($package->i18n('issue_tracker_tag_name_required'));
            }
            $tag = Tag::getByName($name);
            if (!$tag) {
                $tag = new Tag();
                $tag->setName($name);
                $count = (int) rex_sql::factory()->getArray('SELECT COUNT(*) c FROM ' . rex::getTable('issue_tracker_tags'))[0]['c'];
                $tag->setColor(self::COLORS[$count % count(self::COLORS)]);
                $tag->save();
            }
            if ($issue) {
                $issue->addTag($tag->getId());
            }
            $this->send([
                'success' => true,
                'tag' => ['id' => $tag->getId(), 'name' => $tag->getName(), 'color' => $tag->getColor()],
                'tags' => $issue ? self::tagsOf($issueId) : null,
            ]);
        }

        $this->fail('Unknown action');
    }

    /**
     * @return list<array{id:int,name:string,color:string}>
     */
    private static function tagsOf(int $issueId): array
    {
        $out = [];
        foreach (Tag::getByIssue($issueId) as $tag) {
            $out[] = ['id' => $tag->getId(), 'name' => $tag->getName(), 'color' => $tag->getColor()];
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $data
     * @return never
     */
    private function send(array $data): void
    {
        rex_response::sendJson($data);
        exit;
    }

    /**
     * @return never
     */
    private function fail(string $message, string $status = rex_response::HTTP_BAD_REQUEST): void
    {
        rex_response::setStatus($status);
        rex_response::sendJson(['success' => false, 'message' => $message]);
        exit;
    }
}
