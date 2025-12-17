<?php
namespace App\View;

/**
 * Formate les données du Back, et les expose au Front
 */
class VersionView extends AbstractView
{
    /**
     * @var array Raw version data provided by the Controller
     */
    private array $versionData;

    public function __construct(array $versionData)
    {
        $this->versionData = $versionData;
    }


    /**
     * Returns the version name formatted for display.
     *
     * @return string
     */
    public function getVersionName(): string
    {
        return htmlspecialchars($this->versionData['name']);
    }


    /**
     * Returns the release date (if available).
     *
     * @return string|null
     */
    public function getReleaseDate(): ?string
    {
        return $this->versionData['releaseDate'] ?? null;
    }
    

    /**
     * Indicates whether the version has been released.
     *
     * @return bool
     */
    public function isReleased(): bool
    {
        return (bool) ($this->versionData['released'] ?? false);
    }
}