<?php

/**
 * Media Manager Effekt für Issue Tracker Attachments
 * WICHTIG: Muss im globalen Namespace bleiben!
 *
 * @package issue_tracker
 */
class rex_effect_issue_attachment extends rex_effect_abstract
{
    public function execute()
    {
        $filename = $this->media->getMediaFilename();
        
        // Pfad zum Attachment
        $filepath = rex_path::addonData('issue_tracker', 'attachments/' . $filename);
        
        if (!is_file($filepath)) {
            return;
        }
        
        $this->media->setSourcePath($filepath);
        $this->media->setFormat(strtolower(rex_file::extension($filename)));
        
        // Bei Bildern: als Bild laden
        if ($this->media->getFormat() && in_array($this->media->getFormat(), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $this->media->asImage();
        }
    }

    public function getName()
    {
        return 'Issue Tracker Attachment';
    }

    public function getParams()
    {
        return [];
    }
}
