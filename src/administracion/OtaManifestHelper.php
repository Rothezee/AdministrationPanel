<?php
declare(strict_types=1);

/**
 * Descarga y valida ota/manifest.json (GitHub raw o URL fija), con caché en disco.
 *
 * Variables de entorno:
 * - GITHUB_REPO: owner/repo (ej. Rothezee/ReporteGrua)
 * - OTA_MANIFEST_URL: URL HTTPS completa al JSON (override)
 * - OTA_MANIFEST_REF: rama/ref del raw (default main) si no hay OTA_MANIFEST_URL
 * - GITHUB_TOKEN: opcional, header Authorization para raw API / rate limit
 * - OTA_ALLOWED_BRANCHES: lista separada por comas; vacío = todas las del manifiesto
 * - OTA_MANIFEST_CACHE_TTL: segundos (default 120)
 */
final class OtaManifestHelper
{
    private const DEFAULT_TTL = 120;

    /** @return array{branches: array<string, array{version:string,url:string,sha256:string}>} */
    public static function loadManifest(): array
    {
        $url = self::manifestUrl();
        $ttl = (int) (getenv('OTA_MANIFEST_CACHE_TTL') ?: self::DEFAULT_TTL);
        if ($ttl < 5) {
            $ttl = 5;
        }

        $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ap_ota_manifest_' . md5($url) . '.json';
        if (is_readable($cacheFile)) {
            $raw = @file_get_contents($cacheFile);
            if ($raw !== false) {
                $wrap = json_decode($raw, true);
                if (is_array($wrap) && isset($wrap['t'], $wrap['data']) && (time() - (int) $wrap['t']) < $ttl) {
                    return self::validateManifestData($wrap['data']);
                }
            }
        }

        // PHP: "header" debe ser un string CRLF; ignore_errors permite leer cuerpo y $http_response_header.
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 20,
                'header' => self::httpHeaderBlock(),
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = file_get_contents($url, false, $ctx);
        if ($body === false) {
            throw new RuntimeException('No se pudo descargar el manifiesto OTA: ' . $url);
        }

        $status = 0;
        if (!empty($http_response_header) && is_array($http_response_header)) {
            $first = (string) $http_response_header[0];
            if (preg_match('#\s(\d{3})\s#', $first, $m)) {
                $status = (int) $m[1];
            }
        }
        if ($status >= 400) {
            $snippet = strlen($body) > 120 ? substr($body, 0, 120) . '…' : $body;
            throw new RuntimeException(
                'Manifiesto OTA: HTTP ' . $status . ' al obtener ' . $url . ($snippet !== '' ? ' — ' . $snippet : '')
            );
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            $hint = trim(strip_tags($body));
            if (strlen($hint) > 180) {
                $hint = substr($hint, 0, 180) . '…';
            }
            throw new RuntimeException(
                'Manifiesto OTA: respuesta no es JSON' . ($hint !== '' ? ' — ' . $hint : '')
            );
        }

        $validated = self::validateManifestData($data);
        @file_put_contents(
            $cacheFile,
            json_encode(['t' => time(), 'data' => $data], JSON_UNESCAPED_UNICODE)
        );

        return $validated;
    }

    public static function manifestUrl(): string
    {
        $override = trim((string) (getenv('OTA_MANIFEST_URL') ?: ''));
        if ($override !== '') {
            if (!preg_match('#^https://#i', $override)) {
                throw new InvalidArgumentException('OTA_MANIFEST_URL debe ser https://');
            }
            return $override;
        }

        $repo = trim((string) (getenv('GITHUB_REPO') ?: 'Rothezee/ReporteGrua'));
        if (!preg_match('#^[a-zA-Z0-9_.-]+/[a-zA-Z0-9_.-]+$#', $repo)) {
            throw new InvalidArgumentException('GITHUB_REPO inválido (esperado owner/repo)');
        }

        $ref = trim((string) (getenv('OTA_MANIFEST_REF') ?: 'main'));
        $ref = preg_replace('#[^a-zA-Z0-9/_.-]#', '', $ref) ?: 'main';

        return 'https://raw.githubusercontent.com/' . $repo . '/' . $ref . '/ota/manifest.json';
    }

    /** @return list<string> */
    public static function allowedBranchIds(array $manifest): array
    {
        $keys = array_keys($manifest['branches']);
        $allow = trim((string) (getenv('OTA_ALLOWED_BRANCHES') ?: ''));
        if ($allow === '') {
            return $keys;
        }
        $set = array_map('trim', explode(',', $allow));
        $set = array_filter($set, static function ($s) {
            return $s !== '';
        });
        return array_values(array_intersect($keys, $set));
    }

    /**
     * @param array<string, mixed> $data
     * @return array{branches: array<string, array{version:string,url:string,sha256:string}>}
     */
    public static function validateManifestData(array $data): array
    {
        if (!isset($data['branches']) || !is_array($data['branches'])) {
            throw new RuntimeException('Manifiesto: falta objeto "branches"');
        }

        $out = [];
        foreach ($data['branches'] as $name => $entry) {
            if (!is_string($name) || $name === '') {
                continue;
            }
            if (!is_array($entry)) {
                continue;
            }
            $version = isset($entry['version']) ? trim((string) $entry['version']) : '';
            $url = isset($entry['url']) ? trim((string) $entry['url']) : '';
            $sha = isset($entry['sha256']) ? strtolower(trim((string) $entry['sha256'])) : '';

            if ($version === '' || $url === '' || $sha === '') {
                continue;
            }
            if (!preg_match('#^https://#i', $url)) {
                continue;
            }
            if (!preg_match('/^[a-f0-9]{64}$/', $sha)) {
                continue;
            }
            $out[$name] = [
                'version' => $version,
                'url' => $url,
                'sha256' => $sha,
            ];
        }

        if ($out === []) {
            throw new RuntimeException('Manifiesto: no hay ramas válidas (version, url https, sha256 hex64)');
        }

        return ['branches' => $out];
    }

    /** @return array{version:string,url:string,sha256:string} */
    public static function branchEntry(array $manifest, string $branch): array
    {
        $branch = trim($branch);
        if ($branch === '' || !isset($manifest['branches'][$branch])) {
            throw new RuntimeException('Rama OTA no disponible en el manifiesto: ' . $branch);
        }
        return $manifest['branches'][$branch];
    }

    /** Cabeceras HTTP en un solo bloque (requerido por stream_context_create). */
    private static function httpHeaderBlock(): string
    {
        $lines = ['User-Agent: AdministrationPanel-OTA/1.0'];
        $tok = trim((string) (getenv('GITHUB_TOKEN') ?: ''));
        if ($tok !== '') {
            $lines[] = 'Authorization: Bearer ' . $tok;
        }
        $lines[] = 'Accept: application/json, */*;q=0.8';
        return implode("\r\n", $lines) . "\r\n";
    }
}
