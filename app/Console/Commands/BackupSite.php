<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;
use ZipArchive;

/**
 * Daily backup for shared hosting (no shell tools needed): a gzipped SQL dump
 * written in PHP and a zip of the uploaded media, in storage/app/backups.
 * Keeps the newest 7 of each. Download them from cPanel File Manager.
 */
#[Signature('backup:run {--keep=7 : How many backups of each kind to keep} {--no-media : Database only}')]
#[Description('Back up the database and uploaded media to storage/app/backups')]
class BackupSite extends Command
{
    /** Tables that are cache or queue state, not content: structure only. */
    private const SKIP_DATA = ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs'];

    public function handle(): int
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir, 0750);
        // Never reachable from the web, even on a misconfigured host.
        File::put($dir.'/.htaccess', "Require all denied\n");

        $stamp = now()->format('Y-m-d_His');

        try {
            $sql = $this->dumpDatabase("{$dir}/database-{$stamp}.sql.gz");
            $this->info('Database: '.basename($sql).' ('.$this->size($sql).')');

            if (! $this->option('no-media')) {
                $zip = $this->zipMedia("{$dir}/media-{$stamp}.zip");
                $this->info($zip ? 'Media: '.basename($zip).' ('.$this->size($zip).')' : 'Media: nothing to back up.');
            }
        } catch (Throwable $e) {
            report($e);
            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $keep = max(1, (int) $this->option('keep'));
        foreach (['database-*.sql.gz', 'media-*.zip'] as $pattern) {
            $files = glob("{$dir}/{$pattern}") ?: [];
            rsort($files); // newest first (timestamped names)
            foreach (array_slice($files, $keep) as $old) {
                @unlink($old);
            }
        }

        return self::SUCCESS;
    }

    /** SQL dump with CREATE TABLE and INSERTs, written in chunks (low memory). */
    private function dumpDatabase(string $file): string
    {
        $gz = gzopen($file, 'wb6');
        $pdo = DB::connection()->getPdo();

        gzwrite($gz, '-- Grosolve Moving Network backup, '.now()->toDateTimeString()."\nSET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n");

        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            $create = (array) DB::selectOne("SHOW CREATE TABLE `{$table}`");
            gzwrite($gz, "DROP TABLE IF EXISTS `{$table}`;\n".array_values($create)[1].";\n\n");

            if (in_array($table, self::SKIP_DATA, true)) {
                continue;
            }

            DB::table($table)->orderByRaw('1')->lazy(500)->chunk(200)->each(function ($rows) use ($gz, $pdo, $table) {
                $values = $rows->map(fn ($row) => '('.collect((array) $row)->map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v))->implode(',').')');
                $columns = '`'.implode('`,`', array_keys((array) $rows->first())).'`';
                gzwrite($gz, "INSERT INTO `{$table}` ({$columns}) VALUES\n".$values->implode(",\n").";\n");
            });

            gzwrite($gz, "\n");
        }

        gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($gz);

        return $file;
    }

    /** Zip of storage/app/public/media (originals and variants). */
    private function zipMedia(string $file): ?string
    {
        $root = storage_path('app/public/media');

        if (! is_dir($root) || ! File::allFiles($root)) {
            return null;
        }

        $zip = new ZipArchive;
        $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach (File::allFiles($root) as $item) {
            // Images are already compressed: store, don't deflate.
            $name = 'media/'.$item->getRelativePathname();
            $zip->addFile($item->getPathname(), $name);
            $zip->setCompressionName($name, ZipArchive::CM_STORE);
        }

        $zip->close();

        return $file;
    }

    private function size(string $file): string
    {
        $bytes = filesize($file) ?: 0;

        return $bytes > 1048576 ? round($bytes / 1048576, 1).' MB' : max(1, round($bytes / 1024)).' KB';
    }
}
