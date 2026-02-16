<?php

namespace Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Model\Timeline;
use App\Model\Config;

/**
 * Tests unitaires pour la classe Timeline
 *
 * Focus sur les calculs de jours ouvrés avec logique hybride :
 * - Cas spécial : même heure ±30min → jours calendaires
 * - Sinon : tranches horaires (4 x 0.25 jour)
 */
class TimelineTest extends TestCase
{
    private TimelineTestable $timeline;

    /**
     * Initialisation avant chaque test
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->timeline = new TimelineTestable();
    }

    /**
     * ==========================================
     * Tests pour getTimeSlot()
     * ==========================================
     */

    /**
     * @test
     */
    #[Test]
    #[DataProvider('provideTimeSlots')]
    public function it_calculates_time_slot_correctly(
        string $time,
        int $expected,
        string $reason
    ): void {
        $dateTime = new \DateTime("2025-10-17 $time");

        $result = $this->timeline->getTimeSlot($dateTime);

        $this->assertEquals(
            $expected,
            $result,
            "Pour $time : $reason"
        );
    }

    /**
     * Fournit les cas de test pour les tranches horaires
     */
    public static function provideTimeSlots(): array
    {
        return [
            // [heure, tranche attendue, raison]
            ['08:00', 1, 'Avant 10h → tranche 1'],
            ['09:59', 1, 'Avant 10h → tranche 1'],
            ['10:00', 2, '10h-12h59 → tranche 2'],
            ['11:06', 2, '10h-12h59 → tranche 2'],
            ['12:59', 2, '10h-12h59 → tranche 2'],
            ['13:00', 3, '13h-15h59 → tranche 3'],
            ['15:30', 3, '13h-15h59 → tranche 3'],
            ['15:59', 3, '13h-15h59 → tranche 3'],
            ['16:00', 4, 'Après 16h → tranche 4'],
            ['18:37', 4, 'Après 16h → tranche 4'],
            ['23:59', 4, 'Après 16h → tranche 4'],
        ];
    }

    /**
     * ==========================================
     * Tests pour calculateBusinessDays()
     * CAS SPÉCIAL : Même heure ±30min
     * ==========================================
     */

    /**
     * @test
     */
    #[Test]
    public function it_uses_calendar_days_for_same_exact_time(): void
    {
        // Lun 11h → Mar 11h (exactement même heure)
        $start = new \DateTime('2025-10-20 11:00'); // lundi
        $end = new \DateTime('2025-10-21 11:00');   // mardi

        $result = $this->timeline->calculateBusinessDays($start, $end);

        // Cas spécial activé → 1 jour calendaire ouvré (le mardi)
        $this->assertEquals(1, $result, 'Même heure exacte → jours calendaires');
    }

    /**
     * @test
     */
    #[Test]
    public function it_uses_calendar_days_for_similar_time_within_30min(): void
    {
        // Lun 10h30 → Mar 11h00 (diff = 30min, dans la tolérance)
        $start = new \DateTime('2025-10-20 10:30'); // lundi
        $end = new \DateTime('2025-10-21 11:00');   // mardi

        $result = $this->timeline->calculateBusinessDays($start, $end);

        // Cas spécial activé → 1 jour calendaire ouvré
        $this->assertEquals(1, $result, 'Diff 30min → jours calendaires');
    }

    /**
     * @test
     */
    #[Test]
    public function it_uses_calendar_days_for_similar_time_reverse(): void
    {
        // Lun 11h00 → Mar 10h30 (diff = 30min, dans la tolérance)
        $start = new \DateTime('2025-10-20 11:00'); // lundi
        $end = new \DateTime('2025-10-21 10:30');   // mardi

        $result = $this->timeline->calculateBusinessDays($start, $end);

        // Cas spécial activé → 1 jour calendaire ouvré
        $this->assertEquals(1, $result, 'Diff 30min inverse → jours calendaires');
    }

    /**
     * @test
     */
    #[Test]
    public function it_does_not_use_calendar_days_when_diff_exceeds_30min(): void
    {
        // Lun 10h00 → Mar 11h00 (diff = 60min, hors tolérance)
        $start = new \DateTime('2025-10-20 10:00'); // lundi
        $end = new \DateTime('2025-10-21 11:00');   // mardi

        $result = $this->timeline->calculateBusinessDays($start, $end);

        // Cas spécial NON activé → logique par tranches
        // Lun 10h (T2) : T2+T3+T4 = 3 tranches
        // Mar 11h (T2) : T1+T2 = 2 tranches
        // Total = 5 tranches = 1.25 jours
        $this->assertEquals(1.25, $result, 'Diff 60min → logique tranches');
    }

    /**
     * ==========================================
     * Tests pour calculateBusinessDays()
     * LOGIQUE PAR TRANCHES
     * ==========================================
     */

    /**
     * @test
     */
    #[Test]
    public function it_calculates_zero_days_when_end_before_start(): void
    {
        $start = new \DateTime('2025-10-20 10:00');
        $end = new \DateTime('2025-10-15 10:00');

        $result = $this->timeline->calculateBusinessDays($start, $end);

        $this->assertEquals(0, $result, 'Si end < start, retourner 0');
    }

    /**
     * @test
     */
    #[Test]
    public function it_calculates_same_day_2_slots(): void
    {
        // Lun 8h → Lun 12h (T1 + T2 = 2 tranches)
        $start = new \DateTime('2025-10-20 08:00');
        $end = new \DateTime('2025-10-20 12:00');

        $result = $this->timeline->calculateBusinessDays($start, $end);

        $this->assertEquals(0.5, $result, 'Même jour 2 tranches = 0.5 jour');
    }

    /**
     * @test
     */
    #[Test]
    public function it_calculates_same_day_3_slots(): void
    {
        // Lun 8h → Lun 15h30 (T1 + T2 + T3 = 3 tranches)
        $start = new \DateTime('2025-10-20 08:00');
        $end = new \DateTime('2025-10-20 15:30');

        $result = $this->timeline->calculateBusinessDays($start, $end);

        $this->assertEquals(0.75, $result, 'Même jour 3 tranches = 0.75 jour');
    }

    /**
     * @test
     */
    #[Test]
    public function it_calculates_same_day_4_slots(): void
    {
        // Lun 8h → Lun 18h (T1 + T2 + T3 + T4 = 4 tranches)
        $start = new \DateTime('2025-10-20 08:00');
        $end = new \DateTime('2025-10-20 18:00');

        $result = $this->timeline->calculateBusinessDays($start, $end);

        $this->assertEquals(1, $result, 'Même jour 4 tranches = 1 jour');
    }

    /**
     * @test
     */
    #[Test]
    public function it_calculates_different_days_with_slots(): void
    {
        // Lun 8h → Mar 12h
        // Lun (T1+T2+T3+T4) = 4 tranches
        // Mar (T1+T2) = 2 tranches
        // Total = 6 tranches = 1.5 jours
        $start = new \DateTime('2025-10-20 08:00');
        $end = new \DateTime('2025-10-21 12:00');

        $result = $this->timeline->calculateBusinessDays($start, $end);

        $this->assertEquals(1.5, $result, 'Lun 8h → Mar 12h = 1.5 jours');
    }

    /**
     * @test
     */
    #[Test]
    public function it_calculates_different_days_full_days(): void
    {
        // Lun 8h → Mar 18h
        // Lun (4 tranches) + Mar (4 tranches) = 8 tranches = 2 jours
        $start = new \DateTime('2025-10-20 08:00');
        $end = new \DateTime('2025-10-21 18:00');

        $result = $this->timeline->calculateBusinessDays($start, $end);

        $this->assertEquals(2, $result, 'Lun 8h → Mar 18h = 2 jours');
    }

    /**
     * @test
     */
    #[Test]
    public function it_handles_weekend_correctly(): void
    {
        // Ven 10h → Lun 10h (même heure, cas spécial)
        // Sam-Dim exclus → 1 jour (le lundi)
        $start = new \DateTime('2025-10-24 10:00'); // vendredi
        $end = new \DateTime('2025-10-27 10:00');   // lundi

        $result = $this->timeline->calculateBusinessDays($start, $end);

        $this->assertEquals(1, $result, 'Ven → Lun même heure = 1 jour (lundi)');
    }

    /**
     * @test
     */
    #[Test]
    public function it_excludes_weekend_in_between(): void
    {
        // Ven 8h → Lun 18h (pas même heure → tranches)
        // Ven (4 tranches) + Lun (4 tranches) = 8 tranches = 2 jours
        $start = new \DateTime('2025-10-24 08:00'); // vendredi
        $end = new \DateTime('2025-10-27 18:00');   // lundi

        $result = $this->timeline->calculateBusinessDays($start, $end);

        $this->assertEquals(2, $result, 'Ven 8h → Lun 18h = 2 jours (WE exclu)');
    }

    /**
     * @test
     */
    #[Test]
    public function it_excludes_holidays(): void
    {
        // 30/12/2025 10h → 02/01/2026 10h (même heure, cas spécial)
        // 31/12 ouvré + 01/01 FÉRIÉ (exclu) + 02/01 ouvré = 2 jours
        $start = new \DateTime('2025-12-30 10:00'); // mardi
        $end = new \DateTime('2026-01-02 10:00');   // vendredi

        $result = $this->timeline->calculateBusinessDays($start, $end);

        // Note: countBusinessDaysBetween compte de start+1 à end inclus
        // = 31/12 + 02/01 (01/01 exclu) = 2 jours
        $this->assertEquals(2, $result, 'Les jours fériés doivent être exclus');
    }

    /**
     * ==========================================
     * Tests avec cas réels du changelog Jira
     * ==========================================
     */

    /**
     * @test
     * Cas réel : "Affinée" - 17/10 11h06 → 21/10 11h49
     */
    #[Test]
    public function it_calculates_affinee_status_correctly(): void
    {
        $start = new \DateTime('2025-10-17 11:06'); // vendredi
        $end = new \DateTime('2025-10-21 11:49');   // mardi

        $result = $this->timeline->calculateBusinessDays($start, $end);

        // Diff horaire = 43min → hors tolérance → logique tranches
        // Ven 11h06 (T2) : T2+T3+T4 = 3 tranches
        // 18-19 (WE) = 0
        // 20 (lun) = 4 tranches
        // 21 11h49 (T2) : T1+T2 = 2 tranches
        // Total = 9 tranches = 2.25 jours
        $this->assertEquals(2.25, $result, 'Affinée avec nouvelle logique');
    }

    /**
     * @test
     * Cas réel : "A planifier" - 21/10 11h49 → 27/10 16h04
     */
    #[Test]
    public function it_calculates_a_planifier_status_correctly(): void
    {
        $start = new \DateTime('2025-10-21 11:49'); // mardi
        $end = new \DateTime('2025-10-27 16:04');   // lundi

        $result = $this->timeline->calculateBusinessDays($start, $end);

        // Diff > 30min → tranches
        // Mar 11h49 (T2) : T2+T3+T4 = 3
        // 22, 23, 24 (ven) = 3 x 4 = 12
        // 25-26 (WE) = 0
        // Lun 16h04 (T4) : T1+T2+T3+T4 = 4
        // Total = 19 tranches = 4.75 jours
        $this->assertEquals(4.75, $result, 'A planifier avec nouvelle logique');
    }

    /**
     * @test
     * Cas réel : "To Do" - 27/10 16h04 → 10/11 18h37
     */
    #[Test]
    public function it_calculates_to_do_status_correctly(): void
    {
        $start = new \DateTime('2025-10-27 16:04'); // lundi
        $end = new \DateTime('2025-11-10 18:37');   // lundi

        $result = $this->timeline->calculateBusinessDays($start, $end);

        // Diff > 30min → tranches
        // 27/10 16h04 (T4) : T4 = 1
        // 28-31 oct = 4 jours = 16
        // 01-02 nov (WE) = 0
        // 03-07 nov = 5 jours = 20
        // 08-09 nov (WE) = 0
        // 10/11 18h37 (T4) : T1+T2+T3+T4 = 4
        // Total = 41 tranches = 10.25 jours
        $this->assertEquals(10.25, $result, 'To Do avec nouvelle logique');
    }
}

/**
 * Classe testable qui expose les méthodes protected pour les tests
 */
class TimelineTestable extends Timeline
{
    #[Test] public function getTimeSlot(\DateTime $dateTime): int
    {
        return parent::getTimeSlot($dateTime);
    }
}
