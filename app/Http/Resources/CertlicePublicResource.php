<?php

namespace App\Http\Resources;

use Illuminate\Support\Facades\URL;
use Illuminate\Http\Resources\Json\JsonResource;

class CertlicePublicResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $license = $this->license_number;
        $masked = null;
        if ($license) {
            $length = strlen($license);
            $suffix = $length > 4 ? substr($license, -4) : $license;
            $masked = str_repeat('*', max(0, $length - 4)) . $suffix;
        }

        return [
            'id' => (string) $this->id,
            'owner_type' => $this->type,
            'owner_id' => $this->type_id,
            'type' => $this->cert_type,
            'issuer' => $this->issuer,
            'license_number' => $masked,
            'status' => $this->status,
            'issued_at' => $this->issued_at,
            'expires_at' => $this->expires_at,
            'verified_at' => $this->verified_at,
            'file_url' => $this->location,
            'signed_url' => $this->signedUrl(),
            'notes' => $this->notes,
        ];
    }

    private function signedUrl(): ?string
    {
        $filename = $this->filenameFromUrl($this->location);
        if (!$filename) {
            return null;
        }

        $routeName = $this->type === 'hospital' ? 'files.certlice.hospital' : 'files.certlice.carer';

        return URL::temporarySignedRoute(
            $routeName,
            now()->addMinutes(10),
            ['filename' => $filename]
        );
    }

    private function filenameFromUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }
        $path = parse_url($url, PHP_URL_PATH);
        if (!$path) {
            return null;
        }
        return basename($path);
    }
}
