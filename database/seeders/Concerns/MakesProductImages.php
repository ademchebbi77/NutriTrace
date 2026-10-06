<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait MakesProductImages
{
    /**
     * Generate a simple local SVG placeholder so the demo needs no external image.
     */
    protected function placeholderImage(string $name, string $color): string
    {
        $path = 'products/demo-'.Str::slug($name).'.svg';
        $label = htmlspecialchars($name, ENT_XML1);
        $initial = htmlspecialchars(Str::upper(Str::substr($name, 0, 1)), ENT_XML1);

        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 400" role="img" aria-label="{$label}">
          <defs>
            <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0" stop-color="{$color}"/>
              <stop offset="1" stop-color="{$color}" stop-opacity="0.65"/>
            </linearGradient>
          </defs>
          <rect width="600" height="400" fill="#f8f9fc"/>
          <rect width="600" height="400" fill="url(#g)"/>
          <circle cx="300" cy="170" r="95" fill="#ffffff" fill-opacity="0.18"/>
          <text x="300" y="205" font-family="Arial, Helvetica, sans-serif" font-size="110" font-weight="700" fill="#ffffff" text-anchor="middle">{$initial}</text>
          <text x="300" y="335" font-family="Arial, Helvetica, sans-serif" font-size="30" font-weight="600" fill="#ffffff" text-anchor="middle">{$label}</text>
        </svg>
        SVG;

        Storage::disk('public')->put($path, $svg);

        return $path;
    }
}
