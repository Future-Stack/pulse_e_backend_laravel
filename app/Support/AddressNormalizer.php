<?php

namespace App\Support;

/**
 * Lightweight address normalization (spec 2.2: "Normalized (libpostal or
 * equivalent) before matching"). This is a rule-based stand-in, not a full
 * libpostal integration — it handles the common USPS abbreviation
 * inconsistencies that break naive string-equality joins between NPPES and
 * Google Places addresses. For higher-volume production matching, swap
 * normalize() for a real libpostal call (there's a PHP FFI binding, or run
 * libpostal as a sidecar service and call it over HTTP).
 */
class AddressNormalizer
{
    private const STREET_ABBREVIATIONS = [
        'street' => 'st', 'avenue' => 'ave', 'boulevard' => 'blvd', 'drive' => 'dr',
        'lane' => 'ln', 'road' => 'rd', 'court' => 'ct', 'circle' => 'cir',
        'place' => 'pl', 'square' => 'sq', 'terrace' => 'ter', 'parkway' => 'pkwy',
        'highway' => 'hwy', 'suite' => 'ste', 'apartment' => 'apt', 'building' => 'bldg',
        'floor' => 'fl', 'north' => 'n', 'south' => 's', 'east' => 'e', 'west' => 'w',
        'northeast' => 'ne', 'northwest' => 'nw', 'southeast' => 'se', 'southwest' => 'sw',
    ];

    public static function normalize(?string $address): string
    {
        if (! $address) {
            return '';
        }

        $normalized = strtolower(trim($address));
        $normalized = preg_replace('/[.,#]/', '', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        $words = explode(' ', $normalized);
        $words = array_map(fn ($word) => self::STREET_ABBREVIATIONS[$word] ?? $word, $words);

        return trim(implode(' ', $words));
    }

    /** 0.0-1.0 similarity between two normalized addresses. */
    public static function similarity(?string $a, ?string $b): float
    {
        $a = self::normalize($a);
        $b = self::normalize($b);

        if ($a === '' || $b === '') {
            return 0.0;
        }

        similar_text($a, $b, $percent);

        return round($percent / 100, 2);
    }
}
