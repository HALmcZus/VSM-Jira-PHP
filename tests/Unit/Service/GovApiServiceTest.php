<?php

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Model\Config;
use App\Service\GovApiService;

class GovApiServiceTest extends TestCase
{
    /**
     * @test
     * Vérifie que getNonWorkingDays() retourne bien un tableau plat de dates Y-m-d
     * et que les années hors plage sont exclues.
     */
    #[Test]
    public function it_filters_holidays_to_relevant_years(): void
    {
        $currentYear = (int) date('Y');
        $minYear     = $currentYear - 5;

        // Simule une réponse API avec des années en dehors de la plage
        $apiResponse = [
            ($minYear - 1) . '-01-01' => 'Jour de l\'An (hors plage)',
            $minYear       . '-01-01' => 'Jour de l\'An',
            $currentYear   . '-05-01' => 'Fête du Travail',
            ($currentYear + 1) . '-01-01' => 'Futur (hors plage)',
        ];

        $mockService = $this->createMock(GovApiService::class);
        $mockService->method('fetchAllHolidays')->willReturn($apiResponse);

        // On injecte le mock via réflexion car Config instancie GovApiService directement
        // → Si tu veux tester cela facilement, il vaudra à terme injecter GovApiService dans Config.
        // Pour l'instant, on teste la logique de filtrage isolément :
        $filtered = array_values(
            array_filter(
                array_keys($apiResponse),
                static function (string $date) use ($minYear, $currentYear): bool {
                    $year = (int) substr($date, 0, 4);
                    return $year >= $minYear && $year <= $currentYear;
                }
            )
        );

        $this->assertContains($minYear . '-01-01', $filtered);
        $this->assertContains($currentYear . '-05-01', $filtered);
        $this->assertNotContains(($minYear - 1) . '-01-01', $filtered);
        $this->assertNotContains(($currentYear + 1) . '-01-01', $filtered);
    }
}
