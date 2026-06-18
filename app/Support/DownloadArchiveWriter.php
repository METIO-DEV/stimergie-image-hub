<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class DownloadArchiveWriter
{
    private ZipArchive $zip;

    private bool $isOpen = false;

    /**
     * @var array<string, true>
     */
    private array $entryNames = [];

    private function __construct(private readonly string $path)
    {
        $this->zip = new ZipArchive;

        if ($this->zip->open($this->path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Impossible de creer l archive ZIP.');
        }

        $this->isOpen = true;
    }

    public static function create(string $prefix = 'stimergie-archive-'): self
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);

        if ($path === false) {
            throw new RuntimeException('Impossible de creer le fichier temporaire ZIP.');
        }

        return new self($path);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function addDiskFile(string $disk, string $objectKey, string $entryName): string
    {
        $temporaryPath = $this->copyObjectToTemporaryFile($disk, $objectKey);

        try {
            return $this->addLocalFile($temporaryPath, $entryName);
        } finally {
            @unlink($temporaryPath);
        }
    }

    public function addLocalFile(string $sourcePath, string $entryName): string
    {
        $entryName = $this->uniqueEntryName($entryName);

        if (! $this->zip->addFile($sourcePath, $entryName)) {
            throw new RuntimeException("Impossible d ajouter {$entryName} a l archive ZIP.");
        }

        $this->storeEntryWithoutCompression($entryName);
        $this->entryNames[$entryName] = true;
        $this->flushCurrentEntry();

        return $entryName;
    }

    public function finish(): string
    {
        $this->close();

        return $this->path;
    }

    public function cleanup(): void
    {
        $this->closeIgnoringErrors();
        @unlink($this->path);
    }

    private function copyObjectToTemporaryFile(string $disk, string $objectKey): string
    {
        $sourceStream = Storage::disk($disk)->readStream($objectKey);

        if ($sourceStream === false) {
            throw new RuntimeException("Impossible de lire l image {$objectKey}.");
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'stimergie-archive-entry-');

        if ($temporaryPath === false) {
            if (is_resource($sourceStream)) {
                fclose($sourceStream);
            }

            throw new RuntimeException('Impossible de creer un fichier temporaire image.');
        }

        $targetStream = fopen($temporaryPath, 'w');

        if ($targetStream === false) {
            if (is_resource($sourceStream)) {
                fclose($sourceStream);
            }

            @unlink($temporaryPath);

            throw new RuntimeException('Impossible d ouvrir le fichier temporaire image.');
        }

        try {
            if (stream_copy_to_stream($sourceStream, $targetStream) === false) {
                throw new RuntimeException("Impossible de copier l image {$objectKey}.");
            }
        } finally {
            if (is_resource($sourceStream)) {
                fclose($sourceStream);
            }

            fclose($targetStream);
        }

        return $temporaryPath;
    }

    private function uniqueEntryName(string $entryName): string
    {
        if (! isset($this->entryNames[$entryName])) {
            return $entryName;
        }

        $extension = pathinfo($entryName, PATHINFO_EXTENSION);
        $base = $extension !== ''
            ? substr($entryName, 0, -strlen($extension) - 1)
            : $entryName;
        $suffix = 2;

        do {
            $candidate = $extension !== ''
                ? "{$base}-{$suffix}.{$extension}"
                : "{$base}-{$suffix}";
            $suffix++;
        } while (isset($this->entryNames[$candidate]));

        return $candidate;
    }

    /**
     * JPEG, PNG and WebP files are already compressed. Storing them as-is avoids
     * wasting CPU on ZIP deflate and keeps large archives predictable.
     */
    private function storeEntryWithoutCompression(string $entryName): void
    {
        if (method_exists($this->zip, 'setCompressionName')) {
            $this->zip->setCompressionName($entryName, ZipArchive::CM_STORE);
        }
    }

    private function flushCurrentEntry(): void
    {
        $this->close();

        if ($this->zip->open($this->path, ZipArchive::CREATE) !== true) {
            throw new RuntimeException('Impossible de rouvrir l archive ZIP.');
        }

        $this->isOpen = true;
    }

    private function close(): void
    {
        if ($this->isOpen && ! $this->zip->close()) {
            $this->isOpen = false;

            throw new RuntimeException('Impossible de finaliser l archive ZIP.');
        }

        $this->isOpen = false;
    }

    private function closeIgnoringErrors(): void
    {
        if (! $this->isOpen) {
            return;
        }

        @$this->zip->close();
        $this->isOpen = false;
    }
}
