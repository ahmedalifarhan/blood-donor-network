<?php

namespace App\Repositories\Sheets;

use App\Repositories\Table\CellBackend;
use Google\Client as GoogleClient;
use Google\Service\Sheets;
use Google\Service\Sheets\AddSheetRequest;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\GridProperties;
use Google\Service\Sheets\Request as SheetsRequest;
use Google\Service\Sheets\SheetProperties;
use Google\Service\Sheets\ValueRange;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use InvalidArgumentException;

/**
 * Google Sheets implementation of CellBackend (research R2, R4).
 *
 * Writes use valueInputOption=RAW and reads use UNFORMATTED_VALUE, so cells
 * stay exactly as encoded. The Google client is created lazily on first use:
 * missing or invalid credentials surface as a failed call (store unreachable),
 * never as a crash while the app boots.
 */
final class SheetsCellBackend implements CellBackend
{
    private ?Sheets $service = null;

    public function __construct(
        private readonly string $spreadsheetId,
        private readonly string $credentials,
        private readonly ?ClientInterface $httpClient = null,
    ) {}

    public function cacheKey(): string
    {
        return 'sheets:'.$this->spreadsheetId;
    }

    public function title(): string
    {
        $spreadsheet = $this->service()->spreadsheets->get($this->spreadsheetId, ['fields' => 'properties.title']);

        return (string) $spreadsheet->getProperties()->getTitle();
    }

    public function tabNames(): array
    {
        $spreadsheet = $this->service()->spreadsheets->get($this->spreadsheetId, ['fields' => 'sheets.properties.title']);
        $names = [];

        foreach ($spreadsheet->getSheets() as $sheet) {
            $names[] = (string) $sheet->getProperties()->getTitle();
        }

        return $names;
    }

    public function headerRows(array $tabs): array
    {
        if ($tabs === []) {
            return [];
        }

        $response = $this->service()->spreadsheets_values->batchGet($this->spreadsheetId, [
            'ranges' => array_map(static fn (string $tab): string => self::range($tab, '1:1'), $tabs),
            'valueRenderOption' => 'UNFORMATTED_VALUE',
        ]);

        $headers = [];

        foreach ($response->getValueRanges() as $i => $valueRange) {
            $headers[$tabs[$i]] = self::strings(($valueRange->getValues() ?? [])[0] ?? []);
        }

        return $headers;
    }

    public function readTab(string $tab): array
    {
        $values = $this->service()->spreadsheets_values
            ->get($this->spreadsheetId, self::range($tab), ['valueRenderOption' => 'UNFORMATTED_VALUE'])
            ->getValues() ?? [];

        return array_values(array_map(self::strings(...), $values));
    }

    public function addTabs(array $tabs): void
    {
        $requests = array_map(static fn (string $tab): SheetsRequest => new SheetsRequest([
            'addSheet' => new AddSheetRequest([
                'properties' => new SheetProperties([
                    'title' => $tab,
                    'gridProperties' => new GridProperties(['frozenRowCount' => 1]),
                ]),
            ]),
        ]), $tabs);

        $this->service()->spreadsheets->batchUpdate(
            $this->spreadsheetId,
            new BatchUpdateSpreadsheetRequest(['requests' => $requests]),
        );
    }

    public function writeRow(string $tab, int $rowNumber, array $cells): void
    {
        $this->service()->spreadsheets_values->update(
            $this->spreadsheetId,
            self::range($tab, "A{$rowNumber}"),
            new ValueRange(['values' => [$cells]]),
            ['valueInputOption' => 'RAW'],
        );
    }

    public function appendRows(string $tab, array $rows): void
    {
        $this->service()->spreadsheets_values->append(
            $this->spreadsheetId,
            self::range($tab, 'A1'),
            new ValueRange(['values' => array_map('array_values', $rows)]),
            ['valueInputOption' => 'RAW', 'insertDataOption' => 'INSERT_ROWS'],
        );
    }

    private function service(): Sheets
    {
        if ($this->service !== null) {
            return $this->service;
        }

        $client = new GoogleClient;
        $client->setApplicationName('Blood Donor Network');
        $client->setAuthConfig(self::decodeCredentials($this->credentials));
        $client->setScopes([Sheets::SPREADSHEETS]);
        // Short timeouts keep each scheduler call within the 25 s budget (research R3).
        $client->setHttpClient($this->httpClient ?? new GuzzleClient([
            'base_uri' => GoogleClient::API_BASE_PATH,
            'http_errors' => false,
            'timeout' => 10,
            'connect_timeout' => 5,
        ]));

        return $this->service = new Sheets($client);
    }

    /**
     * Accepts the service-account key as raw JSON or base64-encoded JSON.
     *
     * @return array<string, mixed>
     */
    private static function decodeCredentials(string $credentials): array
    {
        $credentials = trim($credentials);
        $json = str_starts_with($credentials, '{') ? $credentials : base64_decode($credentials, true);
        $decoded = is_string($json) ? json_decode($json, true) : null;

        if (! is_array($decoded) || ($decoded['type'] ?? null) !== 'service_account') {
            throw new InvalidArgumentException('GOOGLE_SERVICE_ACCOUNT_JSON is not a valid service-account key.');
        }

        /** @var array<string, mixed> */
        return $decoded;
    }

    private static function range(string $tab, ?string $cells = null): string
    {
        $quoted = "'".str_replace("'", "''", $tab)."'";

        return $cells === null ? $quoted : "{$quoted}!{$cells}";
    }

    /**
     * @param  array<mixed>  $cells
     * @return list<string>
     */
    private static function strings(array $cells): array
    {
        return array_values(array_map(static fn (mixed $cell): string => match (true) {
            is_bool($cell) => $cell ? 'TRUE' : 'FALSE',
            is_scalar($cell) => (string) $cell,
            default => '',
        }, $cells));
    }
}
