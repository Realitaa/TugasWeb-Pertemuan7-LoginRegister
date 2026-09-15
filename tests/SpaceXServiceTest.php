<?php

declare(strict_types=1);

namespace Realitaa\PhpVite\Tests;

use PHPUnit\Framework\TestCase;
use Realitaa\PhpVite\SpaceX\SpaceXService;

class SpaceXServiceTest extends TestCase
{
    private string $tempCacheFile;
    private SpaceXService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempCacheFile = sys_get_temp_dir() . '/test_spacex_cache_' . bin2hex(random_bytes(6)) . '.json';
        $this->service = new SpaceXService($this->tempCacheFile, 3600);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempCacheFile)) {
            @unlink($this->tempCacheFile);
        }
        parent::tearDown();
    }

    public function testParseRatioFormats(): void
    {
        $parsed = $this->service->parseRatio('684 / 687 (99.56%)');
        $this->assertSame('684', $parsed['success']);
        $this->assertSame('687', $parsed['total']);
        $this->assertSame('99.56%', $parsed['rate']);

        $parsedCommas = $this->service->parseRatio('1,200 / 1,250 (96.0%)');
        $this->assertSame('1200', $parsedCommas['success']);
        $this->assertSame('1250', $parsedCommas['total']);
        $this->assertSame('96.0%', $parsedCommas['rate']);

        $empty = $this->service->parseRatio('Not a ratio');
        $this->assertSame('—', $empty['success']);
        $this->assertSame('—', $empty['total']);
        $this->assertSame('—', $empty['rate']);
    }

    public function testParseHtmlStructureAndAscendingYearSorting(): void
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<body>
  <div class="content-box">
    <h3>Launch Count</h3>
    <table>
      <tbody>
        <tr><td>Total</td><td>718 / 730 (98.35%)</td></tr>
        <tr><td>Falcon 9</td><td>684 / 687 (99.56%)</td></tr>
      </tbody>
    </table>
  </div>
  <div class="content-box">
    <h3>Launches Per Year</h3>
    <table>
      <tbody>
        <tr><td>Most in a year</td><td>167 (2025)</td></tr>
        <tr><td>2026</td><td>108 / 108 (100%)</td></tr>
        <tr><td>2024</td><td>136 / 138 (98.55%)</td></tr>
        <tr><td>2025</td><td>167 / 170 (98.24%)</td></tr>
        <tr><td>2023</td><td>96 / 98 (97.96%)</td></tr>
      </tbody>
    </table>
  </div>
</body>
</html>
HTML;

        $stats = $this->service->parseHtml($html);

        $this->assertSame('730', $stats['launch_count']['total_launches']);
        $this->assertSame('718', $stats['launch_count']['successful_launches']);
        $this->assertSame('98.35%', $stats['launch_count']['success_rate']);

        // Check years are chronologically sorted ascending
        $years = array_column($stats['launches_per_year']['by_year'], 'year');
        $this->assertSame(['2023', '2024', '2025', '2026'], $years);
    }

    public function testDefaultStatsStructure(): void
    {
        $default = $this->service->defaultStats();
        $this->assertArrayHasKey('launch_count', $default);
        $this->assertArrayHasKey('launches_per_year', $default);
        $this->assertArrayHasKey('launch_sites', $default);
        $this->assertArrayHasKey('landing_sites', $default);
        $this->assertSame('—', $default['launch_count']['total_launches']);
    }

    public function testGetStatsReadsFromCache(): void
    {
        $cachedData = $this->service->defaultStats();
        $cachedData['launch_count']['total_launches'] = '999';
        $cachedData['launches_per_year']['by_year'] = [
            ['year' => '2025', 'success' => 100, 'total' => 100, 'rate' => '100%'],
            ['year' => '2024', 'success' => 90, 'total' => 90, 'rate' => '100%'],
        ];

        file_put_contents($this->tempCacheFile, json_encode($cachedData));

        $result = $this->service->getStats(false);
        $this->assertSame('999', $result['launch_count']['total_launches']);

        // Verify sorting is also enforced when loading from cache
        $years = array_column($result['launches_per_year']['by_year'], 'year');
        $this->assertSame(['2024', '2025'], $years);
    }
}
