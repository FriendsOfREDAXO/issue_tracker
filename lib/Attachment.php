<?php

namespace FriendsOfREDAXO\IssueTracker;

/**
 * Attachment-Klasse für Issue-Dateianhänge
 *
 * @package issue_tracker
 */
class Attachment
{
    private ?int $id = null;
    private ?int $issueId = null;
    private ?int $commentId = null;
    private string $filename;
    private string $originalFilename;
    private ?string $mimetype = null;
    private int $filesize;
    private int $createdBy;
    private string $createdAt;

    /**
     * Konstruktor
     */
    public function __construct()
    {
        $this->createdAt = date('Y-m-d H:i:s');
    }

    /**
     * Gibt den vollständigen Pfad zur Datei zurück
     *
     * @return string
     */
    public function getPath(): string
    {
        return \rex_path::addonData('issue_tracker', 'attachments/' . $this->filename);
    }

    /**
     * Prüft ob die Datei existiert
     *
     * @return bool
     */
    public function fileExists(): bool
    {
        return is_file($this->getPath());
    }

    /**
     * Setzt die ID
     *
     * @param int $id
     * @return self
     */
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Gibt die ID zurück
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Setzt die Issue-ID
     *
     * @param int $issueId
     * @return self
     */
    public function setIssueId(int $issueId): self
    {
        $this->issueId = $issueId;
        return $this;
    }

    /**
     * Gibt die Issue-ID zurück
     *
     * @return int|null
     */
    public function getIssueId(): ?int
    {
        return $this->issueId;
    }

    /**
     * Setzt die Comment-ID
     *
     * @param int|null $commentId
     * @return self
     */
    public function setCommentId(?int $commentId): self
    {
        $this->commentId = $commentId;
        return $this;
    }

    /**
     * Gibt die Comment-ID zurück
     *
     * @return int|null
     */
    public function getCommentId(): ?int
    {
        return $this->commentId;
    }

    /**
     * Setzt den Dateinamen im Medienpool
     *
     * @param string $filename
     * @return self
     */
    public function setFilename(string $filename): self
    {
        $this->filename = $filename;
        return $this;
    }

    /**
     * Gibt den Dateinamen zurück
     *
     * @return string
     */
    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * Setzt den Original-Dateinamen
     *
     * @param string $originalFilename
     * @return self
     */
    public function setOriginalFilename(string $originalFilename): self
    {
        $this->originalFilename = $originalFilename;
        return $this;
    }

    /**
     * Gibt den Original-Dateinamen zurück
     *
     * @return string
     */
    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    /**
     * Setzt den MIME-Type
     *
     * @param string|null $mimetype
     * @return self
     */
    public function setMimetype(?string $mimetype): self
    {
        $this->mimetype = $mimetype;
        return $this;
    }

    /**
     * Gibt den MIME-Type zurück
     *
     * @return string|null
     */
    public function getMimetype(): ?string
    {
        return $this->mimetype;
    }

    /**
     * Setzt die Dateigröße
     *
     * @param int $filesize
     * @return self
     */
    public function setFilesize(int $filesize): self
    {
        $this->filesize = $filesize;
        return $this;
    }

    /**
     * Gibt die Dateigröße zurück
     *
     * @return int
     */
    public function getFilesize(): int
    {
        return $this->filesize;
    }

    /**
     * Gibt die Dateigröße formatiert zurück
     *
     * @return string
     */
    public function getFormattedFilesize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = $this->filesize;
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Setzt die Ersteller-ID
     *
     * @param int $createdBy
     * @return self
     */
    public function setCreatedBy(int $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    /**
     * Gibt die Ersteller-ID zurück
     *
     * @return int
     */
    public function getCreatedBy(): int
    {
        return $this->createdBy;
    }

    /**
     * Setzt das Erstellungsdatum
     *
     * @param string $createdAt
     * @return self
     */
    public function setCreatedAt(string $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * Gibt das Erstellungsdatum zurück
     *
     * @return string
     */
    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    /**
     * Gibt die URL zur Datei zurück (über Media Manager)
     *
     * @return string
     */
    public function getUrl(): string
    {
        return \rex_media_manager::getUrl('issue_tracker_attachment', $this->filename);
    }

    /**
     * Prüft ob es sich um ein Bild handelt
     *
     * @return bool
     */
    public function isImage(): bool
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $extension = strtolower(\rex_file::extension($this->filename));
        return in_array($extension, $imageExtensions, true);
    }

    /**
     * Prüft ob es sich um ein Video handelt
     *
     * @return bool
     */
    public function isVideo(): bool
    {
        $videoExtensions = ['mp4', 'webm', 'ogg', 'avi', 'mov', 'wmv', 'flv', 'mkv'];
        $extension = strtolower(\rex_file::extension($this->filename));
        return in_array($extension, $videoExtensions, true);
    }

    /**
     * Gibt das passende Font Awesome 6 Icon für den Dateityp zurück
     *
     * @return string
     */
    public function getFileIcon(): string
    {
        $extension = strtolower(\rex_file::extension($this->filename));
        
        // Bilder
        if ($this->isImage()) {
            return 'fa-image';
        }
        
        // Videos
        if ($this->isVideo()) {
            return 'fa-video';
        }
        
        // Dokumente
        $docExtensions = ['pdf', 'doc', 'docx', 'odt', 'rtf'];
        if (in_array($extension, $docExtensions, true)) {
            if ($extension === 'pdf') {
                return 'fa-file-pdf';
            }
            return 'fa-file-word';
        }
        
        // Tabellen
        $spreadsheetExtensions = ['xls', 'xlsx', 'ods', 'csv'];
        if (in_array($extension, $spreadsheetExtensions, true)) {
            return 'fa-file-excel';
        }
        
        // Präsentationen
        $presentationExtensions = ['ppt', 'pptx', 'odp'];
        if (in_array($extension, $presentationExtensions, true)) {
            return 'fa-file-powerpoint';
        }
        
        // Archive
        $archiveExtensions = ['zip', 'rar', '7z', 'tar', 'gz', 'bz2'];
        if (in_array($extension, $archiveExtensions, true)) {
            return 'fa-file-zipper';
        }
        
        // Code
        $codeExtensions = ['php', 'js', 'css', 'html', 'json', 'xml', 'sql', 'py', 'java', 'cpp', 'c', 'h', 'rb', 'go', 'ts'];
        if (in_array($extension, $codeExtensions, true)) {
            return 'fa-file-code';
        }
        
        // Audio
        $audioExtensions = ['mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a', 'wma'];
        if (in_array($extension, $audioExtensions, true)) {
            return 'fa-file-audio';
        }
        
        // Text
        $textExtensions = ['txt', 'md', 'log'];
        if (in_array($extension, $textExtensions, true)) {
            return 'fa-file-lines';
        }
        
        // Standard
        return 'fa-file';
    }

    /**
     * Gibt ein Thumbnail zurück (für Bildvorschau über Media Manager)
     *
     * @return string|null
     */
    public function getThumbnailUrl(): ?string
    {
        if (!$this->isImage() || !$this->fileExists()) {
            return null;
        }

        return \rex_media_manager::getUrl('issue_tracker_thumbnail', $this->filename);
    }

    /**
     * Speichert das Attachment in der Datenbank
     *
     * @return bool
     */
    public function save(): bool
    {
        $sql = \rex_sql::factory();
        $sql->setTable(\rex::getTable('issue_tracker_attachments'));
        $sql->setValue('issue_id', $this->issueId);
        $sql->setValue('comment_id', $this->commentId);
        $sql->setValue('filename', $this->filename);
        $sql->setValue('original_filename', $this->originalFilename);
        $sql->setValue('mimetype', $this->mimetype);
        $sql->setValue('filesize', $this->filesize);
        $sql->setValue('created_by', $this->createdBy);
        $sql->setValue('created_at', $this->createdAt);

        if ($this->id) {
            $sql->setWhere(['id' => $this->id]);
            $sql->update();
        } else {
            $sql->insert();
            $this->id = (int) $sql->getLastId();
        }

        return true;
    }

    /**
     * Löscht das Attachment inklusive Datei
     *
     * @return bool
     */
    public function delete(): bool
    {
        if (!$this->id) {
            return false;
        }

        // Datei löschen
        if ($this->fileExists()) {
            \rex_file::delete($this->getPath());
        }

        // Datenbank-Eintrag löschen
        $sql = \rex_sql::factory();
        $sql->setTable(\rex::getTable('issue_tracker_attachments'));
        $sql->setWhere(['id' => $this->id]);
        $sql->delete();

        return true;
    }

    /**
     * Lädt ein Attachment aus der Datenbank
     *
     * @param int $id
     * @return self|null
     */
    public static function get(int $id): ?self
    {
        $sql = \rex_sql::factory();
        $sql->setQuery('SELECT * FROM ' . \rex::getTable('issue_tracker_attachments') . ' WHERE id = ?', [$id]);

        if ($sql->getRows() === 0) {
            return null;
        }

        $attachment = new self();
        $attachment->setId((int) $sql->getValue('id'));
        $attachment->setIssueId((int) $sql->getValue('issue_id'));
        $attachment->setFilename((string) $sql->getValue('filename'));
        $attachment->setOriginalFilename((string) $sql->getValue('original_filename'));
        $attachment->setMimetype($sql->getValue('mimetype') ? (string) $sql->getValue('mimetype') : null);
        $attachment->setFilesize((int) $sql->getValue('filesize'));
        $attachment->setCreatedBy((int) $sql->getValue('created_by'));
        $attachment->setCreatedAt((string) $sql->getValue('created_at'));

        return $attachment;
    }

    /**
     * Gibt alle Attachments für ein Issue zurück
     *
     * @param int $issueId
     * @return self[]
     */
    public static function getByIssue(int $issueId): array
    {
        $sql = \rex_sql::factory();
        $sql->setQuery(
            'SELECT * FROM ' . \rex::getTable('issue_tracker_attachments') . ' WHERE issue_id = ? AND comment_id IS NULL ORDER BY created_at ASC',
            [$issueId]
        );

        $attachments = [];
        for ($i = 0; $i < $sql->getRows(); $i++) {
            $attachment = new self();
            $attachment->setId((int) $sql->getValue('id'));
            $attachment->setIssueId((int) $sql->getValue('issue_id'));
            $attachment->setCommentId($sql->getValue('comment_id') ? (int) $sql->getValue('comment_id') : null);
            $attachment->setFilename((string) $sql->getValue('filename'));
            $attachment->setOriginalFilename((string) $sql->getValue('original_filename'));
            $attachment->setMimetype($sql->getValue('mimetype') ? (string) $sql->getValue('mimetype') : null);
            $attachment->setFilesize((int) $sql->getValue('filesize'));
            $attachment->setCreatedBy((int) $sql->getValue('created_by'));
            $attachment->setCreatedAt((string) $sql->getValue('created_at'));

            $attachments[] = $attachment;
            $sql->next();
        }

        return $attachments;
    }

    /**
     * Gibt alle Attachments eines Kommentars zurück
     *
     * @param int $commentId
     * @return self[]
     */
    public static function getByComment(int $commentId): array
    {
        $sql = \rex_sql::factory();
        $sql->setQuery(
            'SELECT * FROM ' . \rex::getTable('issue_tracker_attachments') . ' WHERE comment_id = ? ORDER BY created_at ASC',
            [$commentId]
        );

        $attachments = [];
        for ($i = 0; $i < $sql->getRows(); $i++) {
            $attachment = new self();
            $attachment->setId((int) $sql->getValue('id'));
            $attachment->setIssueId($sql->getValue('issue_id') ? (int) $sql->getValue('issue_id') : null);
            $attachment->setCommentId((int) $sql->getValue('comment_id'));
            $attachment->setFilename((string) $sql->getValue('filename'));
            $attachment->setOriginalFilename((string) $sql->getValue('original_filename'));
            $attachment->setMimetype($sql->getValue('mimetype') ? (string) $sql->getValue('mimetype') : null);
            $attachment->setFilesize((int) $sql->getValue('filesize'));
            $attachment->setCreatedBy((int) $sql->getValue('created_by'));
            $attachment->setCreatedAt((string) $sql->getValue('created_at'));

            $attachments[] = $attachment;
            $sql->next();
        }

        return $attachments;
    }

    /**
     * Erstellt Attachments aus hochgeladenen Dateien
     *
     * @param int $issueId
     * @param array $uploadedFiles Array von Dateinamen aus dem Upload
     * @param int $userId
     * @return int Anzahl der hinzugefügten Attachments
     */
    public static function createFromUpload(int $issueId, array $uploadedFiles, int $userId): int
    {
        $count = 0;
        $uploadDir = \rex_path::addonData('issue_tracker', 'attachments/');

        foreach ($uploadedFiles as $fileData) {
            if (empty($fileData['filename'])) {
                continue;
            }

            $filepath = $uploadDir . $fileData['filename'];
            if (!is_file($filepath)) {
                continue;
            }

            $attachment = new self();
            $attachment->setIssueId($issueId);
            $attachment->setFilename($fileData['filename']);
            $attachment->setOriginalFilename($fileData['original_name'] ?? $fileData['filename']);
            $attachment->setMimetype($fileData['type'] ?? null);
            $attachment->setFilesize((int) ($fileData['size'] ?? filesize($filepath)));
            $attachment->setCreatedBy($userId);

            if ($attachment->save()) {
                $count++;
            }
        }

        return $count;
    }
}
