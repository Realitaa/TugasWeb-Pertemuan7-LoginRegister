<?php

declare(strict_types=1);

namespace Realitaa\PhpVite\SpaceX;

use DOMDocument;
use DOMXPath;

class SpaceXService
{
    private string $cacheFile;
    private int $cacheTtl;

    public function __construct(?string $cacheFile = null, int $cacheTtl = 3600)
    {
        $this->cacheFile = $cacheFile ?? dirname(__DIR__, 2) . '/storage/cache/spacex_stats.json';
        $this->cacheTtl = $cacheTtl;
    }

    /**
     * Get SpaceX telemetry statistics with 1-hour file caching.
     *
     * @return array<string, mixed>
     */
    public function getStats(bool $forceRefresh = false): array
    {
        $cacheDir = dirname($this->cacheFile);

        // 1. Check valid cache
        if (!$forceRefresh && file_exists($this->cacheFile)) {
            $mtime = filemtime($this->cacheFile);
            if ($mtime !== false && (time() - $mtime < $this->cacheTtl)) {
                $content = @file_get_contents($this->cacheFile);
                if ($content) {
                    $json = json_decode($content, true);
                    if (is_array($json) && !empty($json['launch_count'])) {
                        return $this->sortYearsAscending($json);
                    }
                }
            }
        }

        // 2. Fetch fresh HTML from remote source
        $html = $this->fetchFreshHtml();
        if ($html !== '') {
            $parsed = $this->parseHtml($html);
            if (!empty($parsed['launch_count']['total_launches']) && $parsed['launch_count']['total_launches'] !== '—') {
                if (!is_dir($cacheDir)) {
                    @mkdir($cacheDir, 0755, true);
                }
                @file_put_contents($this->cacheFile, json_encode($parsed, JSON_PRETTY_PRINT));
                return $parsed;
            }
        }

        // 3. Fallback to expired cache if available
        if (file_exists($this->cacheFile)) {
            $content = @file_get_contents($this->cacheFile);
            if ($content) {
                $json = json_decode($content, true);
                if (is_array($json) && !empty($json['launch_count'])) {
                    return $this->sortYearsAscending($json);
                }
            }
        }

        return $this->defaultStats();
    }

    /**
     * Fetch fresh HTML from SpaceXNow stats page.
     */
    public function fetchFreshHtml(): string
    {
        $url = 'https://spacexnow.com/stats';
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => $userAgent,
            ]);
            $res = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            unset($ch);

            if ($httpCode >= 200 && $httpCode < 300 && is_string($res)) {
                return $res;
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 8,
                    'user_agent' => $userAgent,
                ],
            ]);
            $res = @file_get_contents($url, false, $context);
            if (is_string($res)) {
                return $res;
            }
        }

        return '';
    }

    /**
     * Parse ratio string formatted like "684 / 687 (99.56%)".
     *
     * @return array{success: string, total: string, rate: string}
     */
    public function parseRatio(string $str): array
    {
        $pattern = '/([0-9,]+)\s*\/\s*([0-9,]+)(?:\s*\(([\d\.]+)\%\))?/';
        if (preg_match($pattern, $str, $matches)) {
            return [
                'success' => str_replace(',', '', $matches[1]),
                'total' => str_replace(',', '', $matches[2]),
                'rate' => isset($matches[3]) ? $matches[3] . '%' : '—',
            ];
        }
        return ['success' => '—', 'total' => '—', 'rate' => '—'];
    }

    /**
     * Parse SpaceX statistics HTML content into structured arrays.
     *
     * @return array<string, mixed>
     */
    public function parseHtml(string $html): array
    {
        if (trim($html) === '') {
            return $this->defaultStats();
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $contentBoxes = $xpath->query('//div[contains(@class, "content-box")]');

        $rawSections = [];
        foreach ($contentBoxes as $box) {
            $h3 = $xpath->query('.//h3', $box)->item(0);
            if (!$h3) continue;
            $title = trim($h3->textContent);
            $rows = $xpath->query('.//table/tbody/tr', $box);
            $items = [];
            foreach ($rows as $row) {
                $cols = $xpath->query('./td', $row);
                if ($cols->length >= 2) {
                    $items[] = [
                        'name' => trim($cols->item(0)->textContent),
                        'value' => trim($cols->item(1)->textContent),
                        'extra' => $cols->length >= 3 ? trim($cols->item(2)->textContent) : '',
                    ];
                }
            }
            $rawSections[$title] = $items;
        }

        $stats = $this->defaultStats();

        $findSection = function(string $name) use ($rawSections): ?array {
            foreach ($rawSections as $key => $items) {
                if (strcasecmp((string)$key, $name) === 0) {
                    return $items;
                }
            }
            return null;
        };

        // 1. Launch Count
        $secLaunchCount = $findSection('Launch Count');
        if ($secLaunchCount !== null) {
            foreach ($secLaunchCount as $item) {
                $name = $item['name'];
                $val = $item['value'];
                if (strcasecmp($name, 'Total') === 0) {
                    $p = $this->parseRatio($val);
                    $stats['launch_count']['total_launches'] = $p['total'];
                    $stats['launch_count']['successful_launches'] = $p['success'];
                    $stats['launch_count']['success_rate'] = $p['rate'];
                    if (is_numeric($p['total']) && is_numeric($p['success'])) {
                        $stats['launch_count']['failed_launches'] = (string)((int)$p['total'] - (int)$p['success']);
                    }
                } elseif (stripos($name, 'Most successive') !== false) {
                    $stats['launch_count']['most_successive'] = $val;
                } elseif (stripos($name, 'Successive') !== false) {
                    $stats['launch_count']['successive'] = $val;
                } else {
                    $p = $this->parseRatio($val);
                    $stats['launch_count']['vehicles'][] = [
                        'name' => $name,
                        'success' => (int)($p['success'] !== '—' ? $p['success'] : 0),
                        'total' => (int)($p['total'] !== '—' ? $p['total'] : 0),
                        'rate' => $p['rate'],
                        'raw' => $val,
                    ];
                }
            }
        }

        // 2. Launches per year
        $secLaunchesPerYear = $findSection('Launches Per Year');
        if ($secLaunchesPerYear !== null) {
            foreach ($secLaunchesPerYear as $item) {
                $name = $item['name'];
                $val = $item['value'];
                if (stripos($name, 'Most') !== false) {
                    $stats['launches_per_year']['most_in_year'] = $val;
                } elseif (stripos($name, 'goal') !== false) {
                    $stats['launches_per_year']['goals'][] = ['name' => $name, 'value' => $val];
                } elseif (preg_match('/^\d{4}$/', $name)) {
                    $p = $this->parseRatio($val);
                    $stats['launches_per_year']['by_year'][] = [
                        'year' => $name,
                        'success' => (int)($p['success'] !== '—' ? $p['success'] : 0),
                        'total' => (int)($p['total'] !== '—' ? $p['total'] : 0),
                        'rate' => $p['rate'],
                    ];
                }
            }
            // Sort chronologically ascending (2010 -> 2026)
            usort($stats['launches_per_year']['by_year'], function ($a, $b) {
                return (int)$a['year'] <=> (int)$b['year'];
            });
        }

        // 3. Launch sites
        $secLaunchSites = $findSection('Launch Sites');
        if ($secLaunchSites !== null) {
            foreach ($secLaunchSites as $item) {
                $p = $this->parseRatio($item['value']);
                $stats['launch_sites'][] = [
                    'name' => $item['name'],
                    'success' => (int)($p['success'] !== '—' ? $p['success'] : 0),
                    'total' => (int)($p['total'] !== '—' ? $p['total'] : 0),
                    'rate' => $p['rate'],
                    'raw' => $item['value'],
                ];
            }
        }

        // 4. Landing sites
        $secLandingSites = $findSection('Landing Sites');
        if ($secLandingSites !== null) {
            foreach ($secLandingSites as $item) {
                $p = $this->parseRatio($item['value']);
                $stats['landing_sites'][] = [
                    'name' => $item['name'],
                    'success' => (int)($p['success'] !== '—' ? $p['success'] : 0),
                    'total' => (int)($p['total'] !== '—' ? $p['total'] : 0),
                    'rate' => $p['rate'],
                    'raw' => $item['value'],
                ];
            }
        }

        // 5. Turnarounds
        $secTurnarounds = $findSection('Turnarounds');
        if ($secTurnarounds !== null) {
            foreach ($secTurnarounds as $item) {
                $name = strtolower($item['name']);
                $val = $item['value'];
                $extra = $item['extra'] ?? '';
                if (strpos($name, 'booster') !== false) {
                    $stats['turnarounds']['fastest_booster'] = ['value' => $val, 'details' => $extra];
                } elseif (strpos($name, 'pad') !== false || strpos($name, 'slc') !== false || strpos($name, 'lc-') !== false) {
                    $stats['turnarounds']['sites'][] = ['name' => $item['name'], 'value' => $val, 'details' => $extra];
                } else {
                    $stats['turnarounds']['fastest'] = ['value' => $val, 'details' => $extra];
                }
            }
        }

        // 6. Booster reuse
        $secBoosterReuse = $findSection('Booster Reuse');
        if ($secBoosterReuse !== null) {
            foreach ($secBoosterReuse as $item) {
                $name = strtolower($item['name']);
                $val = $item['value'];
                if (strpos($name, 'most flights') !== false) {
                    $stats['booster_reuse']['most_flights'] = $val;
                } elseif (strpos($name, 'block 5 landed') !== false) {
                    $stats['booster_reuse']['block_5_landed'] = $val;
                } elseif (strpos($name, 'block 5 reflown') !== false) {
                    $stats['booster_reuse']['block_5_reflown'] = $val;
                } elseif (strpos($name, 'landed') !== false) {
                    $stats['booster_reuse']['landed'] = $val;
                } elseif (strpos($name, 'reflown') !== false) {
                    $stats['booster_reuse']['reflown'] = $val;
                }
            }
        }

        // 7. Capsule reuse
        $secCapsuleReuse = $findSection('Capsule Reuse');
        if ($secCapsuleReuse !== null) {
            foreach ($secCapsuleReuse as $item) {
                $name = strtolower($item['name']);
                $val = $item['value'];
                if (strpos($name, 'landed') !== false) {
                    $stats['capsule_reuse']['landed'] = $val;
                } elseif (strpos($name, 'reflown') !== false) {
                    $stats['capsule_reuse']['reflown'] = $val;
                }
            }
        }

        // 8. Dragon
        $secDragon = $findSection('Dragon');
        if ($secDragon !== null) {
            foreach ($secDragon as $item) {
                $name = strtolower($item['name']);
                $val = $item['value'];
                if (strpos($name, 'missions') !== false) {
                    $stats['dragon']['missions'] = $val;
                } elseif (strpos($name, 'iss cargo') !== false) {
                    $stats['dragon']['iss_cargo'] = $val;
                } elseif (strpos($name, 'reflights') !== false) {
                    $stats['dragon']['reflights'] = $val;
                } elseif (strpos($name, 'crew in orbit') !== false) {
                    $stats['dragon']['crew_in_orbit'] = $val;
                } elseif (strpos($name, 'crew flown') !== false) {
                    $stats['dragon']['crew_flown_total'] = $val;
                }
            }
        }

        // 9. Payloads
        $secPayloads = $findSection('Payloads');
        if ($secPayloads !== null) {
            foreach ($secPayloads as $item) {
                $val = $item['value'];
                if (stripos($val, 'will be done soon') !== false) continue;
                $name = strtolower($item['name']);
                if (strpos($name, 'leo') !== false) {
                    $stats['payloads']['heaviest_leo'] = ['value' => $val, 'extra' => $item['extra'] ?? ''];
                } elseif (strpos($name, 'gto') !== false) {
                    $stats['payloads']['heaviest_gto'] = ['value' => $val, 'extra' => $item['extra'] ?? ''];
                } elseif (strpos($name, 'starlinks') !== false) {
                    $stats['payloads']['starlinks_in_orbit'] = ['value' => $val, 'extra' => $item['extra'] ?? ''];
                } elseif (strpos($name, 'teslas') !== false) {
                    $stats['payloads']['teslas_in_space'] = ['value' => $val, 'extra' => $item['extra'] ?? ''];
                }
            }
        }

        // 10. Mars
        $secMars = $findSection('Mars');
        if ($secMars !== null) {
            foreach ($secMars as $item) {
                $name = strtolower($item['name']);
                $val = $item['value'];
                if (strpos($name, 'landing') !== false) {
                    $stats['mars']['landings'] = $val;
                } elseif (strpos($name, 'cargo') !== false) {
                    $stats['mars']['cargo'] = $val;
                } elseif (strpos($name, 'population') !== false) {
                    $stats['mars']['population'] = $val;
                }
            }
        }

        // 11. Moon
        $secMoon = $findSection('Moon');
        if ($secMoon !== null) {
            foreach ($secMoon as $item) {
                $name = strtolower($item['name']);
                $val = $item['value'];
                if (strpos($name, 'landing') !== false) {
                    $stats['moon']['landings'] = $val;
                } elseif (strpos($name, 'population') !== false) {
                    $stats['moon']['population'] = $val;
                }
            }
        }

        $stats['last_updated'] = date('c');

        return $stats;
    }

    /**
     * Provide default fallback structure with placeholders.
     *
     * @return array<string, mixed>
     */
    public function defaultStats(): array
    {
        return [
            'launch_count' => [
                'total_launches' => '—',
                'successful_launches' => '—',
                'success_rate' => '—',
                'failed_launches' => '—',
                'most_successive' => '—',
                'successive' => '—',
                'vehicles' => [],
            ],
            'launches_per_year' => ['most_in_year' => '—', 'goals' => [], 'by_year' => []],
            'launch_sites' => [],
            'landing_sites' => [],
            'turnarounds' => ['fastest' => ['value' => '—', 'details' => ''], 'fastest_booster' => ['value' => '—', 'details' => ''], 'sites' => []],
            'booster_reuse' => ['most_flights' => '—', 'landed' => '—', 'reflown' => '—', 'block_5_landed' => '—', 'block_5_reflown' => '—'],
            'capsule_reuse' => ['landed' => '—', 'reflown' => '—'],
            'dragon' => ['missions' => '—', 'iss_cargo' => '—', 'reflights' => '—', 'crew_in_orbit' => '—', 'crew_flown_total' => '—'],
            'payloads' => ['heaviest_leo' => ['value' => '—', 'extra' => ''], 'heaviest_gto' => ['value' => '—', 'extra' => ''], 'starlinks_in_orbit' => ['value' => '—', 'extra' => ''], 'teslas_in_space' => ['value' => '—', 'extra' => '']],
            'mars' => ['landings' => '—', 'cargo' => '—', 'population' => '—'],
            'moon' => ['landings' => '—', 'population' => '—'],
            'last_updated' => null,
        ];
    }

    /**
     * Ensure years array in statistics is sorted chronologically ascending.
     *
     * @param array<string, mixed> $stats
     * @return array<string, mixed>
     */
    private function sortYearsAscending(array $stats): array
    {
        if (!empty($stats['launches_per_year']['by_year']) && is_array($stats['launches_per_year']['by_year'])) {
            usort($stats['launches_per_year']['by_year'], function ($a, $b) {
                return (int)($a['year'] ?? 0) <=> (int)($b['year'] ?? 0);
            });
        }
        return $stats;
    }
}
