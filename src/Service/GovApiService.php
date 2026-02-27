<?php

namespace App\Service;

/**
 * GovApiService
 *
 * Accès à l'API publique du gouvernement français.
 * Actuellement utilisé pour récupérer les jours fériés (calendrier.api.gouv.fr).
 *
 * Les résultats sont mis en cache sur disque pendant 1 an (fichier JSON),
 * ainsi qu'en mémoire statique pour la durée de la requête PHP courante.
 *
 * @see https://calendrier.api.gouv.fr/jours-feries/
 */
class GovApiService
{
    const BASE_URL_NON_WORKING_DAYS = 'https://calendrier.api.gouv.fr/jours-feries';
    const ZONE_METROPOLE = 'metropole';
    const CACHE_DIR      = __DIR__ . '/../../cache';
    const CACHE_TTL      = 365 * 24 * 3600; // 1 an en secondes

    /**
     * Cache mémoire statique : évite les lectures disque répétées
     * au sein d'une même requête PHP (Config est instancié plusieurs fois).
     *
     * @var array<string, array<string, string>>
     */
    private static array $memoryCache = [];

    /**
     * Retourne tous les jours fériés disponibles pour une zone donnée.
     *
     * Ordre de priorité :
     * 1. Cache mémoire (même requête)
     * 2. Cache fichier valide (< 1 an)
     * 3. Appel HTTP → mise en cache fichier + mémoire
     *
     * @param  string $zone Zone géographique (défaut : 'metropole')
     * @return array<string, string>  [date Y-m-d => nom du jour férié]
     *
     * @throws \RuntimeException Si l'appel HTTP échoue ou si la réponse est invalide
     */
    public function fetchAllHolidays(string $zone = self::ZONE_METROPOLE): array
    {
        // 1. Cache mémoire
        if (isset(self::$memoryCache[$zone])) {
            return self::$memoryCache[$zone];
        }

        // 2. Cache fichier
        $cached = $this->readFromFileCache($zone);
        if ($cached !== null) {
            self::$memoryCache[$zone] = $cached;
            return $cached;
        }

        // 3. Appel API
        $url  = sprintf('%s/%s.json', self::BASE_URL_NON_WORKING_DAYS, urlencode($zone));
        $data = $this->get($url);

        $this->writeToFileCache($zone, $data);
        self::$memoryCache[$zone] = $data;

        return $data;
    }

    /**
     * Lit le cache fichier pour une zone donnée.
     *
     * Le fichier cache est un JSON contenant :
     * - "cached_at" : timestamp Unix de la mise en cache
     * - "data"      : la réponse API [date => nom]
     *
     * Retourne null si le fichier est absent ou expiré (> 1 an).
     *
     * @param  string $zone
     * @return array<string, string>|null
     */
    private function readFromFileCache(string $zone): ?array
    {
        $path = $this->getCacheFilePath($zone);

        if (!file_exists($path)) {
            return null;
        }

        $content = json_decode(file_get_contents($path), true);

        // Fichier corrompu ou expiré
        if (!isset($content['cached_at'], $content['data'])) {
            return null;
        }

        if ((time() - $content['cached_at']) > self::CACHE_TTL) {
            return null;
        }

        return $content['data'];
    }

    /**
     * Écrit les données dans le cache fichier pour une zone donnée.
     *
     * Crée le répertoire cache/ s'il n'existe pas.
     * Le fichier contient le timestamp de mise en cache et les données.
     *
     * @param  string               $zone
     * @param  array<string, string> $data
     * @return void
     *
     * @throws \RuntimeException Si l'écriture échoue
     */
    private function writeToFileCache(string $zone, array $data): void
    {
        $dir = self::CACHE_DIR;

        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new \RuntimeException("GovApiService : impossible de créer le répertoire cache ($dir)");
        }

        $content = json_encode([
            'cached_at' => time(),
            'data'      => $data,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

        if (file_put_contents($this->getCacheFilePath($zone), $content) === false) {
            throw new \RuntimeException("GovApiService : impossible d'écrire le cache ({$this->getCacheFilePath($zone)})");
        }
    }

    /**
     * Retourne le chemin absolu du fichier cache pour une zone donnée.
     *
     * @param  string $zone
     * @return string  Ex: /path/to/project/cache/holidays_metropole.json
     */
    private function getCacheFilePath(string $zone): string
    {
        return self::CACHE_DIR . '/holidays_' . preg_replace('/[^a-z0-9_-]/i', '', $zone) . '.json';
    }

    /**
     * Effectue une requête HTTP GET via cURL et retourne le résultat décodé.
     *
     * @param  string $url
     * @return array<string, string>
     *
     * @throws \RuntimeException Si cURL échoue, HTTP non-200, ou JSON invalide
     */
    private function get(string $url): array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("GovApiService::get() — cURL error: $error (URL: $url)");
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \RuntimeException("GovApiService::get() — HTTP $httpCode (URL: $url)");
        }

        $data = json_decode($response, true);

        if (!is_array($data)) {
            throw new \RuntimeException("GovApiService::get() — Réponse JSON invalide (URL: $url)");
        }

        return $data;
    }
}
