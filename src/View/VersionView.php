<?php

namespace App\View;

use App\Model\Version;

/**
 * VersionView
 *
 * Formate et expose les données spécifiques à une Version Jira.
 * Les données communes (métriques, timeline) sont héritées d'AbstractCollectionView.
 */
class VersionView extends AbstractCollectionView
{
    private Version $version;

    public function __construct(Version $version)
    {
        parent::__construct($version);
        $this->version = $version;
    }

    public function getVersionId(): string
    {
        return $this->version->getId() ?? '?';
    }

    public function getVersionName(): string
    {
        return $this->version->getName() ?? '<i>Nom de version non renseigné.</i>';
    }

    public function getVersionDescription(): string
    {
        return $this->version->getDescription()
            ? htmlspecialchars($this->version->getDescription(), ENT_QUOTES, 'UTF-8')
            : '<i>Description non renseignée.</i>';
    }

    public function getVersionUrl(): string
    {
        return $this->version->getUrl();
    }
    public function getVersionStartDate(): string
    {
        return $this->version->getStartDate() ?? '<i>Date non renseignée.</i>';
    }
    public function getVersionReleaseDate(): string
    {
        return $this->version->getReleaseDate() ?? '<i>Non renseignée.</i>';
    }
    public function isVersionReleased(): bool
    {
        return $this->version->isReleased();
    }
    public function isVersionOverdue(): bool
    {
        return $this->version->isOverdue();
    }
    public function getProjectId(): mixed
    {
        return $this->version->getProjectId();
    }
}
