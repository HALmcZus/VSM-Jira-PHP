<?php

namespace Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Utils\FakeConfig;
use Tests\Utils\IssueBuilder;

/**
 * Tests unitaires pour le calcul des Waiting Times
 *
 * Couverture :
 * - Issue sans attente
 * - Issue avec labels d'attente uniquement
 * - Issue avec statuts d'attente uniquement
 * - Issue avec labels + statuts (pas de double comptage)
 * - Plusieurs issues simultanées (agrégation Collection)
 */
class WaitingTimeTest extends TestCase
{
    private FakeConfig $config;
    private IssueBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        // Pas de jours fériés → jours ouvrés = jours de semaine uniquement
        $this->config  = new FakeConfig();
        $this->builder = new IssueBuilder($this->config);
    }

    // ==========================================
    // Tests Issue-level : aucune attente
    // ==========================================

    /**
     * @test
     */
    #[Test]
    public function it_returns_zero_when_no_waiting(): void
    {
        $issue = $this->builder
            ->withCreatedDate('2025-10-20T08:00:00+00:00') // lundi
            ->withResolutionDate('2025-10-21T08:00:00+00:00') // mardi
            ->withStatusTransition('2025-10-21T08:00:00+00:00', 'To Do', 'Done')
            ->build();

        $this->assertEquals(0.0, $issue->getTimeSpentInWaiting());
        $this->assertEmpty($issue->getWaitingTimes());
    }

    // ==========================================
    // Tests Issue-level : labels d'attente
    // ==========================================

    /**
     * @test
     */
    #[Test]
    public function it_calculates_waiting_time_from_label_only(): void
    {
        // Lun 08h : label "attente-externe" ajouté
        // Mer 08h : label "attente-externe" retiré (même heure ±30min → jours calendaires = 2 jours)
        $issue = $this->builder
            ->withCreatedDate('2025-10-20T08:00:00+00:00')    // lundi
            ->withResolutionDate('2025-10-24T18:00:00+00:00') // vendredi
            ->withStatusTransition('2025-10-24T18:00:00+00:00', 'In Progress', 'Done')
            ->withLabelChange(
                '2025-10-20T08:00:00+00:00',
                '',
                'attente-externe'
            )
            ->withLabelChange(
                '2025-10-22T08:00:00+00:00',
                'attente-externe',
                ''
            )
            ->build();

        // Lun 08h → Mer 08h, même heure → 2 jours calendaires ouvrés (Mar, Mer)
        $this->assertEquals(2.0, $issue->getTimeSpentInWaiting());
        $this->assertArrayHasKey('attente-externe', $issue->getWaitingTimes());
        $this->assertEquals(2.0, $issue->getWaitingTimes()['attente-externe']);
    }

    /**
     * @test
     */
    #[Test]
    public function it_accumulates_same_label_applied_twice(): void
    {
        // Label posé 2 fois
        $issue = $this->builder
            ->withCreatedDate('2025-10-20T08:00:00+00:00')
            ->withResolutionDate('2025-10-27T18:00:00+00:00') // lundi suivant
            ->withStatusTransition('2025-10-27T18:00:00+00:00', 'In Progress', 'Done')
            ->withLabelChange('2025-10-20T08:00:00+00:00', '', 'attente-externe')
            ->withLabelChange('2025-10-21T08:00:00+00:00', 'attente-externe', '') // retiré mardi
            ->withLabelChange('2025-10-22T08:00:00+00:00', '', 'attente-externe') // reposé mercredi
            ->withLabelChange('2025-10-23T08:00:00+00:00', 'attente-externe', '') // retiré jeudi
            ->build();

        // 1er passage : Lun→Mar = 1 jour, 2e passage : Mer→Jeu = 1 jour → total 2 jours
        $this->assertEquals(2.0, $issue->getTimeSpentInWaiting());
        $this->assertEquals(2.0, $issue->getWaitingTimes()['attente-externe']);
    }

    // ==========================================
    // Tests Issue-level : statuts d'attente
    // ==========================================

    /**
     * @test
     */
    #[Test]
    public function it_calculates_waiting_time_from_status_only(): void
    {
        // To Do → En Attente (lun 08h) → In Progress (mer 08h) → Done (jeu 08h)
        // Lun→Mer même heure → 2 jours
        $issue = $this->builder
            ->withCreatedDate('2025-10-20T08:00:00+00:00')
            ->withResolutionDate('2025-10-23T08:00:00+00:00')
            ->withStatusTransition('2025-10-20T08:00:00+00:00', 'To Do', 'En Attente')
            ->withStatusTransition('2025-10-22T08:00:00+00:00', 'En Attente', 'In Progress')
            ->withStatusTransition('2025-10-23T08:00:00+00:00', 'In Progress', 'Done')
            ->build();

        $this->assertEquals(2.0, $issue->getTimeSpentInWaiting());
        $this->assertArrayHasKey('En Attente', $issue->getWaitingTimes());
        $this->assertEquals(2.0, $issue->getWaitingTimes()['En Attente']);
    }

    // ==========================================
    // Tests Issue-level : labels + statuts — pas de double comptage
    // ==========================================

    /**
     * @test
     */
    #[Test]
    public function it_does_not_double_count_when_label_and_status_both_present(): void
    {
        // Statut "En Attente" : Lun→Mer = 2j
        // Label "attente-externe" : Jeu→Ven = 1j
        // Total attendu : 3j (pas 2+2+1=5)
        $issue = $this->builder
            ->withCreatedDate('2025-10-20T08:00:00+00:00')
            ->withResolutionDate('2025-10-24T18:00:00+00:00')
            ->withStatusTransition('2025-10-20T08:00:00+00:00', 'To Do', 'En Attente')
            ->withStatusTransition('2025-10-22T08:00:00+00:00', 'En Attente', 'In Progress')
            ->withLabelChange('2025-10-23T08:00:00+00:00', '', 'attente-externe')
            ->withLabelChange('2025-10-24T08:00:00+00:00', 'attente-externe', '')
            ->withStatusTransition('2025-10-24T18:00:00+00:00', 'In Progress', 'Done')
            ->build();

        $waitingTimes = $issue->getWaitingTimes();

        $this->assertArrayHasKey('En Attente', $waitingTimes);
        $this->assertEquals(2.0, $waitingTimes['En Attente'], 'Statut En Attente = 2j');

        $this->assertArrayHasKey('attente-externe', $waitingTimes);
        $this->assertEquals(1.0, $waitingTimes['attente-externe'], 'Label attente-externe = 1j');

        // Total = somme exacte, sans double comptage
        $this->assertEquals(3.0, $issue->getTimeSpentInWaiting(), 'Total waiting = 3j, pas de double comptage');
    }

    /**
     * @test
     */
    #[Test]
    public function it_does_not_double_count_concurrent_label_and_status(): void
    {
        // Label et statut d'attente appliqués SIMULTANÉMENT sur la même période
        // Chacun vaut 2j mais le total ne doit pas dépasser 4j
        $issue = $this->builder
            ->withCreatedDate('2025-10-20T08:00:00+00:00')
            ->withResolutionDate('2025-10-23T18:00:00+00:00')
            ->withStatusTransition('2025-10-20T08:00:00+00:00', 'To Do', 'En Attente')
            ->withLabelChange('2025-10-20T08:00:00+00:00', '', 'attente-externe')
            ->withLabelChange('2025-10-22T08:00:00+00:00', 'attente-externe', '')
            ->withStatusTransition('2025-10-22T08:00:00+00:00', 'En Attente', 'Done')
            ->build();

        $waitingTimes = $issue->getWaitingTimes();

        // 2j pour le statut + 2j pour le label = 4j (deux entrées distinctes, pas de fusion)
        $this->assertEquals(2.0, $waitingTimes['En Attente'] ?? 0.0);
        $this->assertEquals(2.0, $waitingTimes['attente-externe'] ?? 0.0);
        $this->assertEquals(4.0, $issue->getTimeSpentInWaiting());
    }

    // ==========================================
    // Tests Collection-level — agrégation
    // ==========================================

    /**
     * @test
     */
    #[Test]
    public function it_aggregates_waiting_times_across_multiple_issues_without_double_count(): void
    {
        // Issue 1 : 2j de statut "En Attente"
        $issue1 = $this->builder
            ->withCreatedDate('2025-10-20T08:00:00+00:00')
            ->withResolutionDate('2025-10-23T08:00:00+00:00')
            ->withStatusTransition('2025-10-20T08:00:00+00:00', 'To Do', 'En Attente')
            ->withStatusTransition('2025-10-22T08:00:00+00:00', 'En Attente', 'In Progress')
            ->withStatusTransition('2025-10-23T08:00:00+00:00', 'In Progress', 'Done')
            ->build();

        // Issue 2 : 1j de label "attente-externe"
        $issue2 = $this->builder
            ->withCreatedDate('2025-10-20T08:00:00+00:00')
            ->withResolutionDate('2025-10-22T08:00:00+00:00')
            ->withLabelChange('2025-10-20T08:00:00+00:00', '', 'attente-externe')
            ->withLabelChange('2025-10-21T08:00:00+00:00', 'attente-externe', '')
            ->withStatusTransition('2025-10-22T08:00:00+00:00', 'In Progress', 'Done')
            ->build();

        $this->assertEquals(2.0, $issue1->getTimeSpentInWaiting(), 'Issue 1 = 2j');
        $this->assertEquals(1.0, $issue2->getTimeSpentInWaiting(), 'Issue 2 = 1j');

        // Simulation du calcul Collection : additionner les totaux par issue
        $totalWaiting = $issue1->getTimeSpentInWaiting() + $issue2->getTimeSpentInWaiting();
        $this->assertEquals(3.0, $totalWaiting, 'Collection : 2j + 1j = 3j, sans double comptage');
    }
}
