<?php

namespace App\View;

use App\Model\Feature;

/**
 * FeatureView
 *
 * Formate et expose les données spécifiques à une Feature Jira.
 * Les données communes (métriques, timeline) sont héritées d'AbstractCollectionView.
 */
class FeatureView extends AbstractCollectionView
{
    private Feature $feature;

    public function __construct(Feature $feature)
    {
        parent::__construct($feature);
        $this->feature = $feature;
    }

    public function getFeatureId(): string
    {
        return $this->feature->getId() ?? '?';
    }
    public function getFeatureKey(): string
    {
        return $this->feature->getKey() ?? '?';
    }

    public function getFeatureName(): string
    {
        return $this->feature->getName() ?? '<i>Nom de feature non renseigné.</i>';
    }

    public function getFeatureUrl(): string
    {
        return $this->feature->getUrl();
    }
    public function getStatusName(): string
    {
        return $this->feature->getStatusName() ?? '—';
    }
    public function getPlanningIntervals(): string
    {
        return implode(', ', $this->feature->getPlanningIntervals());
    }

    /**
     * Retourne le Planning Interval formaté pour l'affichage.
     * Ex : "PI 7" ou null si non renseigné.
     */
    public function getPlanningIntervalLabel(): ?string
    {
        $lastPI = $this->feature->getPlanningInterval();
        return $lastPI !== null ? 'PI ' . $lastPI : null;
    }
}
