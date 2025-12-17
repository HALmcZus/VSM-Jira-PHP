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
    private array $versionIssues;

    public function __construct(array $versionData, array $versionIssues)
    {
        $this->versionData = $versionData;
        $this->versionIssues = $versionIssues;
    }


    public function getIssues()
    {
        return $this->versionIssues;
    }
    

    public function getVersionId(): string
    {
        return $this->versionData['id'] ?? '';
    }
    

    public function getVersionName(): string
    {
        return $this->versionData['name'] ?? '';
    }


    public function getVersionDescription(): string
    {
        return $this->versionData['description'] ?? '';
    }


    public function getVersionStartDate(): string
    {
        return $this->versionData['startDate'] ?? '';
    }


    public function getVersionReleaseDate(): string
    {
        return $this->versionData['releaseDate'] ?? '';
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

    // /**
    //  * Returns the version name formatted for display.
    //  *
    //  * @return string
    //  */
    // public function getVersionName(): string
    // {
    //     return htmlspecialchars($this->versionData['name']);
    // }


    // /**
    //  * Returns the release date (if available).
    //  *
    //  * @return string|null
    //  */
    // public function getReleaseDate(): ?string
    // {
    //     return $this->versionData['releaseDate'] ?? null;
    // }
    

    // /**
    //  * Indicates whether the version has been released.
    //  *
    //  * @return bool
    //  */
    // public function isReleased(): bool
    // {
    //     return (bool) ($this->versionData['released'] ?? false);
    // }

    // /* ===========================
    //  * Identité
    //  * =========================== */

    // public function getId(): ?string
    // {
    //     return $this->get('id');
    // }

    // public function getName(): string
    // {
    //     return (string) $this->get('name', '');
    // }

    // public function getDescription(): ?string
    // {
    //     return $this->get('description');
    // }

    // public function getProjectId(): ?int
    // {
    //     return $this->get('projectId');
    // }

    // /* ===========================
    //  * États
    //  * =========================== */

    // public function isArchived(): bool
    // {
    //     return (bool) $this->get('archived', false);
    // }

    // public function isReleased(): bool
    // {
    //     return (bool) $this->get('released', false);
    // }

    // public function isOverdue(): bool
    // {
    //     return (bool) $this->get('overdue', false);
    // }

    // /* ===========================
    //  * Dates (format API)
    //  * =========================== */

    // /**
    //  * Date de début brute (YYYY-MM-DD)
    //  */
    // public function getStartDate(): ?string
    // {
    //     return $this->get('startDate');
    // }

    // /**
    //  * Date de release brute (YYYY-MM-DD)
    //  */
    // public function getReleaseDate(): ?string
    // {
    //     return $this->get('releaseDate');
    // }

    // /* ===========================
    //  * Dates (format utilisateur Jira)
    //  * =========================== */

    // /**
    //  * Date de début formatée par Jira (ex: 29/juin/25)
    //  */
    // public function getUserStartDate(): ?string
    // {
    //     return $this->get('userStartDate');
    // }

    // /**
    //  * Date de release formatée par Jira (ex: 31/juil./25)
    //  */
    // public function getUserReleaseDate(): ?string
    // {
    //     return $this->get('userReleaseDate');
    // }

    // /* ===========================
    //  * Helpers d'affichage (optionnels mais pratiques)
    //  * =========================== */

    // /**
    //  * Nom sécurisé pour affichage HTML
    //  */
    // public function getEscapedName(): string
    // {
    //     return htmlspecialchars($this->getName(), ENT_QUOTES, 'UTF-8');
    // }

    // /**
    //  * Description sécurisée pour affichage HTML
    //  */
    // public function getEscapedDescription(): ?string
    // {
    //     $description = $this->getDescription();

    //     return $description !== null
    //         ? htmlspecialchars($description, ENT_QUOTES, 'UTF-8')
    //         : null;
    // }

    // /**
    //  * Libellé d'état humain
    //  */
    // public function getStatusLabel(): string
    // {
    //     if ($this->isReleased()) {
    //         return 'Released';
    //     }

    //     if ($this->isArchived()) {
    //         return 'Archived';
    //     }

    //     return 'In progress';
    // }
}