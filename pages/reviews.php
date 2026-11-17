<?php

/**
 * Review-Runden: anlegen, Teilnehmer (Backend-Benutzer und Gäste) einladen, Zugänge sperren.
 *
 * @package issue_tracker
 */

use FriendsOfREDAXO\IssueTracker\Project;
use FriendsOfREDAXO\IssueTracker\Review\Links;
use FriendsOfREDAXO\IssueTracker\Review\Mailer;
use FriendsOfREDAXO\IssueTracker\Review\Participant;
use FriendsOfREDAXO\IssueTracker\Review\Round;
use FriendsOfREDAXO\IssueTracker\UiHelper;

$package = rex_addon::get('issue_tracker');
$user = rex::requireUser();
if (!$user->isAdmin() && !$user->hasPerm('issue_tracker[issue_manager]')) {
    echo rex_view::error($package->i18n('issue_tracker_no_permission'));
    return;
}

$func = rex_request('func', 'string', '');
$roundId = rex_request('round_id', 'int', 0);
$self = static fn (array $params = []): string => rex_url::backendPage('issue_tracker/reviews', $params);
$message = '';

// ---------------------------------------------------------------------------------------------
// Aktionen (nur POST mit CSRF-Token)
// ---------------------------------------------------------------------------------------------
if ('post' === rex_request_method() && UiHelper::csrfCheck()) {
    $action = rex_post('action', 'string', '');

    if ('save_round' === $action) {
        $round = $roundId > 0 ? Round::get($roundId) : new Round();
        if (null !== $round) {
            $round->title = trim(rex_post('title', 'string', '')) ?: $package->i18n('issue_tracker_review_round_untitled');
            $round->description = trim(rex_post('description', 'string', ''));
            $round->status = rex_post('status', 'string', 'active');
            $round->projectId = rex_post('project_id', 'int', 0) ?: null;
            $round->assignedUserId = rex_post('assigned_user_id', 'int', 0) ?: null;
            $round->category = trim(rex_post('category', 'string', ''));
            $round->startArticleId = rex_post('start_article_id', 'int', 0) ?: null;
            $round->articleIds = array_values(array_filter(array_map('intval', explode(',', rex_post('article_ids', 'string', '')))));
            $expires = rex_post('expires_at', 'string', '');
            $round->expiresAt = preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires) ? new DateTimeImmutable($expires . ' 23:59:59') : null;
            if (0 === $round->id) {
                $round->createdBy = (int) $user->getId();
            }
            $round->save();
            // Wer die Runde anlegt, nimmt teil (sieht die Leiste im Frontend)
            if (null === Participant::forUser($round->id, (int) $user->getId())) {
                $me = new Participant();
                $me->roundId = $round->id;
                $me->userId = (int) $user->getId();
                $me->save();
            }
            rex_response::sendRedirect($self(['func' => 'round', 'round_id' => $round->id, 'msg' => 'saved']));
        }
    }

    $round = Round::get($roundId);
    if (null !== $round) {
        if ('add_user' === $action) {
            $uid = rex_post('user_id', 'int', 0);
            if ($uid > 0 && null === Participant::forUser($round->id, $uid)) {
                $p = new Participant();
                $p->roundId = $round->id;
                $p->userId = $uid;
                $p->save();
            }
            $message = rex_view::success($package->i18n('issue_tracker_review_participant_added'));
        } elseif ('add_guest' === $action) {
            $email = trim(rex_post('email', 'string', ''));
            $name = trim(rex_post('name', 'string', ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = rex_view::error($package->i18n('issue_tracker_review_invalid_email'));
            } else {
                $p = new Participant();
                $p->roundId = $round->id;
                $p->name = $name;
                $p->email = $email;
                $p->save();
                if (rex_post('send_invite', 'bool', false)) {
                    $message = Mailer::invite($p, $round, rex_post('invite_message', 'string', ''))
                        ? rex_view::success($package->i18n('issue_tracker_review_invite_sent', $email))
                        : rex_view::warning($package->i18n('issue_tracker_review_invite_failed', $email));
                } else {
                    $message = rex_view::success($package->i18n('issue_tracker_review_participant_added'));
                }
            }
        } elseif (in_array($action, ['invite', 'link', 'revoke', 'remove', 'notify'], true)) {
            $p = Participant::get(rex_post('participant_id', 'int', 0));
            if (null !== $p && $p->roundId === $round->id) {
                if ('invite' === $action) {
                    $message = Mailer::invite($p, $round)
                        ? rex_view::success($package->i18n('issue_tracker_review_invite_sent', $p->email))
                        : rex_view::warning($package->i18n('issue_tracker_review_invite_failed', $p->email));
                } elseif ('link' === $action) {
                    // Link nur jetzt sichtbar (gespeichert ist nur der Hash)
                    $url = Links::url($round->startUrl(), Links::create($p, $round));
                    $message = rex_view::info($package->i18n('issue_tracker_review_link_once') . '<br><input type="text" class="form-control" readonly value="' . rex_escape($url) . '" data-it-select-all>');
                } elseif ('revoke' === $action) {
                    $p->revoke();
                    $message = rex_view::success($package->i18n('issue_tracker_review_revoked'));
                } elseif ('remove' === $action) {
                    $p->delete();
                    $message = rex_view::success($package->i18n('issue_tracker_review_removed'));
                } elseif ('notify' === $action) {
                    $p->notify = !$p->notify;
                    $p->save();
                }
            }
        } elseif ('delete_round' === $action) {
            $round->delete();
            rex_response::sendRedirect($self(['msg' => 'deleted']));
        }
    }
}

$msg = rex_get('msg', 'string', '');
if ('saved' === $msg) {
    $message .= rex_view::success($package->i18n('issue_tracker_review_round_saved'));
} elseif ('deleted' === $msg) {
    $message .= rex_view::success($package->i18n('issue_tracker_review_round_deleted'));
}
echo $message;

$csrf = UiHelper::csrf()->getHiddenField();
$users = [];
foreach (rex_sql::factory()->getArray('SELECT id FROM ' . rex::getTable('user') . ' WHERE status = 1 ORDER BY name') as $row) {
    $u = rex_user::get((int) $row['id']);
    if (null !== $u && ($u->isAdmin() || $u->hasPerm('issue_tracker[]'))) {
        $users[(int) $u->getId()] = UiHelper::userName($u, $u->getLogin());
    }
}
$fmt = static fn (?string $date): string => null !== $date ? date('d.m.Y H:i', (int) strtotime($date)) : '–';

// ---------------------------------------------------------------------------------------------
// Runde bearbeiten / anlegen
// ---------------------------------------------------------------------------------------------
if ('edit' === $func) {
    $round = $roundId > 0 ? Round::get($roundId) : new Round();
    if (null === $round) {
        echo rex_view::error($package->i18n('issue_tracker_review_round_not_found'));
        return;
    }
    $categories = json_decode((string) (rex_sql::factory()->getArray('SELECT setting_value FROM ' . rex::getTable('issue_tracker_settings') . ' WHERE setting_key = "categories"')[0]['setting_value'] ?? '[]'), true) ?: [];
    if (!in_array('Review', $categories, true)) {
        array_unshift($categories, 'Review');
    }
    $projects = Project::getAll();
    $opt = static fn (string $value, string $label, string $current): string => '<option value="' . rex_escape($value) . '"' . ($value === $current ? ' selected' : '') . '>' . rex_escape($label) . '</option>';

    $form = '<form method="post" action="' . $self(['func' => 'edit', 'round_id' => $round->id]) . '">' . $csrf
        . '<input type="hidden" name="action" value="save_round">'
        . '<div class="form-group"><label for="rv-title">' . $package->i18n('issue_tracker_review_round_title') . '</label>'
        . '<input class="form-control" id="rv-title" name="title" required maxlength="255" value="' . rex_escape($round->title) . '" placeholder="' . rex_escape($package->i18n('issue_tracker_review_round_title_ph')) . '"></div>'
        . '<div class="form-group"><label for="rv-desc">' . $package->i18n('issue_tracker_review_round_description') . '</label>'
        . '<textarea class="form-control" id="rv-desc" name="description" rows="3">' . rex_escape($round->description) . '</textarea>'
        . '<p class="help-block">' . $package->i18n('issue_tracker_review_round_description_hint') . '</p></div>'
        . '<div class="row"><div class="col-sm-4 form-group"><label for="rv-project">' . $package->i18n('issue_tracker_project') . '</label><select class="form-control" id="rv-project" name="project_id"><option value="0">–</option>'
        . implode('', array_map(static fn ($p) => $opt((string) $p->getId(), $p->getName(), (string) ($round->projectId ?? 0)), $projects)) . '</select></div>'
        . '<div class="col-sm-4 form-group"><label for="rv-assignee">' . $package->i18n('issue_tracker_review_round_assignee') . '</label><select class="form-control" id="rv-assignee" name="assigned_user_id"><option value="0">' . $package->i18n('issue_tracker_review_round_assignee_none') . '</option>'
        . implode('', array_map(static fn (int $id, string $name) => $opt((string) $id, $name, (string) ($round->assignedUserId ?? 0)), array_keys($users), $users)) . '</select></div>'
        . '<div class="col-sm-4 form-group"><label for="rv-cat">' . $package->i18n('issue_tracker_category') . '</label><select class="form-control" id="rv-cat" name="category">'
        . implode('', array_map(static fn (string $c) => $opt($c, $c, '' !== $round->category ? $round->category : 'Review'), $categories)) . '</select></div></div>'
        . '<div class="row"><div class="col-sm-6 form-group"><label>' . $package->i18n('issue_tracker_review_round_start') . '</label>' . rex_var_link::getWidget(1, 'start_article_id', (string) ($round->startArticleId ?? '')) . '</div>'
        . '<div class="col-sm-6 form-group"><label for="rv-exp">' . $package->i18n('issue_tracker_review_round_expires') . '</label><input type="date" class="form-control" id="rv-exp" name="expires_at" value="' . ($round->expiresAt?->format('Y-m-d') ?? '') . '"></div></div>'
        . '<div class="form-group"><label>' . $package->i18n('issue_tracker_review_round_scope') . '</label>' . rex_var_linklist::getWidget(2, 'article_ids', implode(',', $round->articleIds))
        . '<p class="help-block">' . $package->i18n('issue_tracker_review_round_scope_hint') . '</p></div>'
        . '<div class="form-group"><label for="rv-status">' . $package->i18n('issue_tracker_status') . '</label><select class="form-control" id="rv-status" name="status">'
        . $opt('active', $package->i18n('issue_tracker_review_round_active'), $round->status) . $opt('closed', $package->i18n('issue_tracker_review_round_closed'), $round->status) . '</select></div>'
        . '<button class="btn btn-save" type="submit">' . $package->i18n('issue_tracker_save') . '</button> '
        . '<a class="btn btn-default" href="' . ($round->id > 0 ? $self(['func' => 'round', 'round_id' => $round->id]) : $self()) . '">' . $package->i18n('issue_tracker_cancel') . '</a>'
        . '</form>';

    $fragment = new rex_fragment();
    $fragment->setVar('title', $round->id > 0 ? $package->i18n('issue_tracker_review_round_edit') : $package->i18n('issue_tracker_review_round_new'), false);
    $fragment->setVar('body', $form, false);
    echo $fragment->parse('core/page/section.php');
    return;
}

// ---------------------------------------------------------------------------------------------
// Eine Runde: Teilnehmer und Einladungen
// ---------------------------------------------------------------------------------------------
if ('round' === $func) {
    $round = Round::get($roundId);
    if (null === $round) {
        echo rex_view::error($package->i18n('issue_tracker_review_round_not_found'));
        return;
    }
    $counts = $round->counts();
    $btn = static function (string $action, int $pid, string $label, string $class = 'btn-default', string $confirm = '') use ($self, $round, $csrf): string {
        return '<form method="post" action="' . $self(['func' => 'round', 'round_id' => $round->id]) . '" class="it-review-inline">' . $csrf
            . '<input type="hidden" name="action" value="' . $action . '"><input type="hidden" name="participant_id" value="' . $pid . '">'
            . '<button type="submit" class="btn btn-xs ' . $class . '"' . ('' !== $confirm ? ' data-confirm="' . rex_escape($confirm) . '"' : '') . '>' . $label . '</button></form>';
    };

    $info = '<dl class="dl-horizontal it-review-facts">'
        . '<dt>' . $package->i18n('issue_tracker_status') . '</dt><dd>' . ($round->isOpen() ? '<span class="label label-success">' . $package->i18n('issue_tracker_review_round_active') . '</span>' : '<span class="label label-default">' . $package->i18n('issue_tracker_review_round_closed') . '</span>') . '</dd>'
        . '<dt>' . $package->i18n('issue_tracker_review_round_expires') . '</dt><dd>' . ($round->expiresAt?->format('d.m.Y') ?? '–') . '</dd>'
        . '<dt>' . $package->i18n('issue_tracker_review_round_scope') . '</dt><dd>' . ([] === $round->articleIds ? $package->i18n('issue_tracker_review_round_scope_all') : rex_escape(implode(', ', array_map(static fn (int $id) => rex_article::get($id)?->getName() ?? '#' . $id, $round->articleIds)))) . '</dd>'
        . '<dt>' . $package->i18n('issue_tracker_review_round_comments') . '</dt><dd>' . $package->i18n('issue_tracker_review_round_counts', (string) $counts['open'], (string) $counts['total'])
        . ' · <a href="' . rex_url::backendPage('issue_tracker/issues/list', ['filter_category' => '' !== $round->category ? $round->category : 'Review'] + (null !== $round->projectId ? ['filter_project' => $round->projectId] : [])) . '">' . $package->i18n('issue_tracker_review_round_show_issues') . '</a></dd>'
        . '</dl>'
        . '<p><a class="btn btn-primary" href="' . rex_escape(Links::url($round->startUrl(), 'on')) . '" target="_blank" rel="noopener"><i class="rex-icon fa-external-link" aria-hidden="true"></i> ' . $package->i18n('issue_tracker_review_open_frontend') . '</a> '
        . '<a class="btn btn-default" href="' . $self(['func' => 'edit', 'round_id' => $round->id]) . '"><i class="rex-icon fa-pencil" aria-hidden="true"></i> ' . $package->i18n('issue_tracker_review_round_edit') . '</a></p>';

    $rows = '';
    foreach (Participant::byRound($round->id) as $p) {
        $actions = [];
        if ($p->isGuest() && $p->isActive()) {
            $actions[] = $btn('invite', $p->id, '<i class="rex-icon fa-envelope-o" aria-hidden="true"></i> ' . ($p->invitedAt ? $package->i18n('issue_tracker_review_reinvite') : $package->i18n('issue_tracker_review_invite')));
            $actions[] = $btn('link', $p->id, '<i class="rex-icon fa-link" aria-hidden="true"></i> ' . $package->i18n('issue_tracker_review_new_link'));
            $actions[] = $btn('notify', $p->id, $p->notify ? $package->i18n('issue_tracker_review_mails_on') : $package->i18n('issue_tracker_review_mails_off'));
            $actions[] = $btn('revoke', $p->id, $package->i18n('issue_tracker_review_revoke'), 'btn-warning', $package->i18n('issue_tracker_review_revoke_confirm'));
        }
        $actions[] = $btn('remove', $p->id, '<i class="rex-icon fa-trash" aria-hidden="true"></i><span class="sr-only">' . $package->i18n('issue_tracker_review_remove') . '</span>', 'btn-delete', $package->i18n('issue_tracker_review_remove_confirm'));
        $rows .= '<tr' . ($p->isActive() ? '' : ' class="text-muted"') . '><td><strong>' . rex_escape($p->label()) . '</strong>' . ($p->isGuest() ? '<br><small>' . rex_escape($p->email) . '</small>' : '') . '</td>'
            . '<td>' . ($p->isGuest() ? ($p->isActive() ? ($p->invitedAt ? $package->i18n('issue_tracker_review_invited_at', $fmt($p->invitedAt)) : $package->i18n('issue_tracker_review_not_invited')) : $package->i18n('issue_tracker_review_revoked_at', $fmt($p->revokedAt))) : $package->i18n('issue_tracker_review_backend_user')) . '</td>'
            . '<td>' . $fmt($p->lastSeenAt) . '</td><td class="text-right it-review-actions">' . implode(' ', $actions) . '</td></tr>';
    }
    $table = '<table class="table table-hover"><thead><tr><th>' . $package->i18n('issue_tracker_review_participant') . '</th><th>' . $package->i18n('issue_tracker_review_access') . '</th><th>' . $package->i18n('issue_tracker_review_last_seen') . '</th><th></th></tr></thead><tbody>' . $rows . '</tbody></table>';

    $guestForm = '<form method="post" action="' . $self(['func' => 'round', 'round_id' => $round->id]) . '">' . $csrf . '<input type="hidden" name="action" value="add_guest">'
        . '<div class="row"><div class="col-sm-4 form-group"><label for="rv-gname">' . $package->i18n('issue_tracker_review_guest_name') . '</label><input class="form-control" id="rv-gname" name="name" maxlength="100" autocomplete="off"></div>'
        . '<div class="col-sm-5 form-group"><label for="rv-gmail">' . $package->i18n('issue_tracker_review_guest_email') . '</label><input type="email" class="form-control" id="rv-gmail" name="email" required maxlength="190" autocomplete="off"></div></div>'
        . '<div class="form-group"><label for="rv-gmsg">' . $package->i18n('issue_tracker_review_invite_message') . '</label><textarea class="form-control" id="rv-gmsg" name="invite_message" rows="2"></textarea></div>'
        . '<div class="checkbox"><label><input type="checkbox" name="send_invite" value="1" checked> ' . $package->i18n('issue_tracker_review_send_invite') . '</label></div>'
        . '<button type="submit" class="btn btn-save"><i class="rex-icon fa-user-plus" aria-hidden="true"></i> ' . $package->i18n('issue_tracker_review_add_guest') . '</button></form>';
    $userForm = '<form method="post" action="' . $self(['func' => 'round', 'round_id' => $round->id]) . '" class="form-inline">' . $csrf . '<input type="hidden" name="action" value="add_user">'
        . '<label for="rv-user" class="sr-only">' . $package->i18n('issue_tracker_review_add_user') . '</label><select class="form-control" id="rv-user" name="user_id">'
        . implode('', array_map(static fn (int $id, string $name) => '<option value="' . $id . '">' . rex_escape($name) . '</option>', array_keys($users), $users))
        . '</select> <button type="submit" class="btn btn-default">' . $package->i18n('issue_tracker_review_add_user') . '</button>'
        . '<p class="help-block">' . $package->i18n('issue_tracker_review_add_user_hint') . '</p></form>';

    $fragment = new rex_fragment();
    $fragment->setVar('title', rex_escape($round->title), false);
    $fragment->setVar('body', $info, false);
    echo $fragment->parse('core/page/section.php');

    $fragment = new rex_fragment();
    $fragment->setVar('title', $package->i18n('issue_tracker_review_participants'), false);
    $fragment->setVar('content', $table, false);
    echo $fragment->parse('core/page/section.php');

    $fragment = new rex_fragment();
    $fragment->setVar('title', $package->i18n('issue_tracker_review_add_guest'), false);
    $fragment->setVar('body', $guestForm . '<hr>' . $userForm, false);
    echo $fragment->parse('core/page/section.php');

    echo '<form method="post" action="' . $self(['round_id' => $round->id]) . '">' . $csrf
        . '<input type="hidden" name="action" value="delete_round"><button type="submit" class="btn btn-delete" data-confirm="' . rex_escape($package->i18n('issue_tracker_review_round_delete_confirm')) . '">' . $package->i18n('issue_tracker_review_round_delete') . '</button></form>';
    return;
}

// ---------------------------------------------------------------------------------------------
// Übersicht
// ---------------------------------------------------------------------------------------------
$rows = '';
foreach (Round::all() as $round) {
    $counts = $round->counts();
    $people = Participant::byRound($round->id);
    $guests = count(array_filter($people, static fn (Participant $p): bool => $p->isGuest() && $p->isActive()));
    $rows .= '<tr><td><a href="' . $self(['func' => 'round', 'round_id' => $round->id]) . '"><strong>' . rex_escape($round->title) . '</strong></a>'
        . (null !== $round->projectId && ($project = Project::get($round->projectId)) ? '<br><small>' . rex_escape($project->getName()) . '</small>' : '') . '</td>'
        . '<td>' . ($round->isOpen() ? '<span class="label label-success">' . $package->i18n('issue_tracker_review_round_active') . '</span>' : '<span class="label label-default">' . $package->i18n('issue_tracker_review_round_closed') . '</span>') . '</td>'
        . '<td>' . (count($people) - $guests) . ' / ' . $guests . '</td>'
        . '<td>' . $package->i18n('issue_tracker_review_round_counts', (string) $counts['open'], (string) $counts['total']) . '</td>'
        . '<td>' . ($round->expiresAt?->format('d.m.Y') ?? '–') . '</td></tr>';
}
$intro = '<p>' . $package->i18n('issue_tracker_review_intro') . '</p>'
    . '<p><a class="btn btn-save" href="' . $self(['func' => 'edit']) . '"><i class="rex-icon fa-plus" aria-hidden="true"></i> ' . $package->i18n('issue_tracker_review_round_new') . '</a></p>';
$table = '' !== $rows
    ? '<table class="table table-hover"><thead><tr><th>' . $package->i18n('issue_tracker_review_round_title') . '</th><th>' . $package->i18n('issue_tracker_status') . '</th><th>' . $package->i18n('issue_tracker_review_people') . '</th><th>' . $package->i18n('issue_tracker_review_round_comments') . '</th><th>' . $package->i18n('issue_tracker_review_round_expires') . '</th></tr></thead><tbody>' . $rows . '</tbody></table>'
    : '<p class="text-muted">' . $package->i18n('issue_tracker_review_none') . '</p>';

$fragment = new rex_fragment();
$fragment->setVar('title', $package->i18n('issue_tracker_reviews'), false);
$fragment->setVar('body', $intro . $table, false);
echo $fragment->parse('core/page/section.php');
