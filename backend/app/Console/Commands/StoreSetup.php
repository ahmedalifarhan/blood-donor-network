<?php

namespace App\Console\Commands;

use App\Repositories\Contracts\StoreSchemaManager;
use App\Repositories\Data\SchemaChange;
use App\Services\ConfigCheck;
use App\Support\StoreUnavailable;
use Illuminate\Console\Command;

/**
 * Creates missing tabs and headers in the data store (FR-033).
 * Runs on the operator's machine or in GitHub Actions, never on Render
 * (research R8). See contracts/operator-commands.md.
 */
final class StoreSetup extends Command
{
    public const EXIT_OK = 0;

    public const EXIT_UNREACHABLE = 1;

    public const EXIT_CONFIG_MISSING = 2;

    public const EXIT_ABORTED = 3;

    protected $signature = 'store:setup
        {--dry-run : Show what would change without writing}
        {--force : Skip the confirmation for a non-sandbox store}
        {--json : Print the change list as JSON}';

    protected $description = 'Create missing tabs and headers in the data store (never deletes or reorders)';

    public function handle(ConfigCheck $config, StoreSchemaManager $schema): int
    {
        $missing = $config->missingForStore();

        if ($missing !== []) {
            $this->error('Missing configuration (set these environment variables):');

            foreach ($missing as $name) {
                $this->line("  - {$name}");
            }

            return self::EXIT_CONFIG_MISSING;
        }

        $dryRun = (bool) $this->option('dry-run');

        try {
            if (! $dryRun && ! $this->confirmedForStore($schema->title())) {
                $this->warn('Aborted. Nothing was changed.');

                return self::EXIT_ABORTED;
            }

            $changes = $dryRun ? $schema->plan() : $schema->apply();
        } catch (StoreUnavailable) {
            $this->error('The data store is unreachable. Check the spreadsheet ID, its sharing, and the service account.');

            return self::EXIT_UNREACHABLE;
        }

        $this->report($changes, $dryRun);

        return self::EXIT_OK;
    }

    private function confirmedForStore(string $title): bool
    {
        if (str_ends_with($title, '[sandbox]') || $this->option('force')) {
            return true;
        }

        return $this->confirm("The spreadsheet \"{$title}\" is not a sandbox store. Apply the changes?");
    }

    /** @param list<SchemaChange> $changes */
    private function report(array $changes, bool $dryRun): void
    {
        if ($this->option('json')) {
            $this->line((string) json_encode([
                'dryRun' => $dryRun,
                'changes' => array_map(
                    static fn (SchemaChange $c): array => ['kind' => $c->kind, 'name' => $c->name],
                    $changes,
                ),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return;
        }

        if ($changes === []) {
            $this->info('✔ no changes');

            return;
        }

        if ($dryRun) {
            $this->comment('(dry run, nothing written)');
        }

        foreach ($changes as $change) {
            $this->line($change->describe());
        }
    }
}
