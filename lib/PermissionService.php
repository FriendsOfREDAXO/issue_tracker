<?php

declare(strict_types=1);

namespace FriendsOfREDAXO\IssueTracker;

use rex;
use rex_user;

/**
 * Service-Klasse für zentralisierte Berechtigungsprüfungen
 * 
 * Vermeidet wiederholte rex::getUser()->isAdmin() und hasPerm()-Aufrufe
 * durch zentrale, wiederverwendbare Methoden.
 * 
 * @package issue_tracker
 */
class PermissionService
{
    /**
     * Gibt den aktuellen Benutzer zurück
     */
    public static function getUser(): ?rex_user
    {
        return rex::getUser();
    }

    /**
     * Gibt die ID des aktuellen Benutzers zurück
     */
    public static function getUserId(): int
    {
        $user = self::getUser();
        return $user ? (int) $user->getId() : 0;
    }

    /**
     * Prüft ob ein Benutzer eingeloggt ist
     */
    public static function isLoggedIn(): bool
    {
        return self::getUser() !== null;
    }

    /**
     * Prüft ob der aktuelle Benutzer ein Admin ist
     */
    public static function isAdmin(): bool
    {
        $user = self::getUser();
        return $user !== null && $user->isAdmin();
    }

    /**
     * Prüft ob der aktuelle Benutzer ein Issue-Manager ist
     */
    public static function isManager(): bool
    {
        $user = self::getUser();
        return $user !== null && $user->hasPerm('issue_tracker[issue_manager]');
    }

    /**
     * Prüft ob der aktuelle Benutzer Admin oder Issue-Manager ist
     */
    public static function isAdminOrManager(): bool
    {
        return self::isAdmin() || self::isManager();
    }

    /**
     * Prüft ob der aktuelle Benutzer Projekte erstellen darf
     * (Nur Admins und Issue-Manager dürfen Projekte erstellen)
     */
    public static function canCreateProject(): bool
    {
        return self::isAdminOrManager();
    }

    /**
     * Prüft ob der aktuelle Benutzer Löschen darf
     * (Admins und Issue-Manager dürfen löschen)
     */
    public static function canDelete(): bool
    {
        return self::isAdminOrManager();
    }

    /**
     * Prüft ob der aktuelle Benutzer ein Issue bearbeiten darf
     * 
     * @param Issue $issue Das zu prüfende Issue
     */
    public static function canEdit(Issue $issue): bool
    {
        if (self::isAdmin()) {
            return true;
        }
        
        $user = self::getUser();
        if ($user === null) {
            return false;
        }
        
        // Ersteller darf immer bearbeiten
        if ($issue->getCreatedBy() === (int) $user->getId()) {
            return true;
        }
        
        // Zugewiesener Benutzer darf bearbeiten
        if ($issue->getAssignedUserId() === (int) $user->getId()) {
            return true;
        }
        
        // Manager dürfen alle Issues bearbeiten
        return self::isManager();
    }

    /**
     * Prüft ob der aktuelle Benutzer ein Issue sehen darf
     * 
     * @param Issue $issue Das zu prüfende Issue
     */
    public static function canView(Issue $issue): bool
    {
        // Nicht-private Issues sind für alle sichtbar
        if (!$issue->getIsPrivate()) {
            return true;
        }
        
        // Private Issues nur für Admins, Manager, Ersteller oder Zugewiesene
        if (self::isAdminOrManager()) {
            return true;
        }
        
        $user = self::getUser();
        if ($user === null) {
            return false;
        }
        
        $userId = (int) $user->getId();
        return $issue->getCreatedBy() === $userId || $issue->getAssignedUserId() === $userId;
    }

    /**
     * Prüft ob der Benutzer Zugriff auf eine bestimmte Domain hat
     * 
     * @param int $domainId Die Domain-ID (Structure Mountpoint)
     */
    public static function hasDomainAccess(int $domainId): bool
    {
        $user = self::getUser();
        if ($user === null) {
            return false;
        }
        
        // Admins haben immer Zugriff
        if ($user->isAdmin()) {
            return true;
        }
        
        // Komplexe Berechtigung prüfen
        $complexPerm = $user->getComplexPerm('structure_mountpoints');
        if ($complexPerm === null) {
            return false;
        }
        
        /** @psalm-suppress UndefinedMethod - hasPerm ist eine dynamische Methode von rex_complex_perm */
        return $complexPerm->hasPerm($domainId);
    }

    /**
     * Prüft ob der Benutzer den Aktivitätsverlauf sehen darf
     */
    public static function canViewHistory(): bool
    {
        return self::isAdminOrManager();
    }

    /**
     * Prüft ob der Benutzer Einstellungen ändern darf
     */
    public static function canManageSettings(): bool
    {
        return self::isAdmin();
    }

    /**
     * Prüft ob der Benutzer Tags verwalten darf
     */
    public static function canManageTags(): bool
    {
        return self::isAdmin();
    }

    /**
     * Prüft ob der Benutzer Kommentare moderieren darf
     * (Pinnen, als Lösung markieren, etc.)
     */
    public static function canModerateComments(): bool
    {
        return self::isAdminOrManager();
    }

    /**
     * Prüft ob der Benutzer einen Kommentar bearbeiten darf
     * 
     * @param Comment $comment Der zu prüfende Kommentar
     */
    public static function canEditComment(Comment $comment): bool
    {
        if (self::isAdminOrManager()) {
            return true;
        }
        
        $user = self::getUser();
        if ($user === null) {
            return false;
        }
        
        // Nur eigene Kommentare bearbeiten
        return $comment->getCreatedBy() === (int) $user->getId();
    }

    /**
     * Prüft ob der Benutzer einen Kommentar löschen darf
     * 
     * @param Comment $comment Der zu prüfende Kommentar
     */
    public static function canDeleteComment(Comment $comment): bool
    {
        // Gleiche Logik wie bearbeiten
        return self::canEditComment($comment);
    }

    /**
     * Prüft ob der Benutzer interne Kommentare sehen darf
     */
    public static function canViewInternalComments(): bool
    {
        return self::isAdminOrManager();
    }

    /**
     * Prüft ob der Benutzer private Issues erstellen darf
     */
    public static function canCreatePrivateIssues(): bool
    {
        return self::isAdmin();
    }

    /**
     * Prüft ob der Benutzer interne Kommentare erstellen darf
     */
    public static function canCreateInternalComments(): bool
    {
        return self::isAdmin();
    }
}
