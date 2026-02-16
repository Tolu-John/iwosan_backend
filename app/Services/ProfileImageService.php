<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileImageService
{
    public function storeUserImage(UploadedFile $file, string $disk, string $directory, string $baseName): string
    {
        $safeBase = Str::slug($baseName);
        $filename = $safeBase . '-' . time() . '.' . $file->extension();

        $file->storeAs($directory, $filename, $disk);

        return $filename;
    }

    public function buildPublicUrl(string $type, string $filename): string
    {
        return url('/') . "/api/storage/{$type}/" . $filename;
    }

    public function deleteIfReplaced(string $disk, string $directory, ?string $currentUrl, ?string $newFilename): void
    {
        if (!$currentUrl) {
            return;
        }

        $currentName = $this->extractFilename($currentUrl);
        if (!$currentName || $currentName === $newFilename) {
            return;
        }

        $path = rtrim($directory, '/') . '/' . $currentName;
        if (Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }

    private function extractFilename(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $basename = basename($path);
        return $basename !== '' ? $basename : null;
    }
}
