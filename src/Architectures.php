<?php

declare(strict_types=1);

namespace SSpkS;

/**
 * Synology platform ("arch") names and the CPU family each one belongs to.
 *
 * A NAS reports its platform (e.g. "geminilake"); packages declare either
 * platforms or a whole family (e.g. "x86_64") in INFO's arch field.
 */
final class Architectures
{
    public const FAMILIES = [
        // ARMv7 (32-bit)
        'alpine' => 'armv7',
        'alpine4k' => 'armv7',
        'armada370' => 'armv7',
        'armada375' => 'armv7',
        'armada38x' => 'armv7',
        'armadaxp' => 'armv7',
        'comcerto2k' => 'armv7',
        'monaco' => 'armv7',
        // ARMv8 (64-bit)
        'armada37xx' => 'armv8',
        'rtd1296' => 'armv8',
        'rtd1296b' => 'armv8',
        'rtd1619b' => 'armv8',
        // Intel Atom (32-bit)
        'evansport' => 'i686',
        // x86_64
        'apollolake' => 'x86_64',
        'avoton' => 'x86_64',
        'braswell' => 'x86_64',
        'broadwell' => 'x86_64',
        'broadwellnk' => 'x86_64',
        'broadwellnkv2' => 'x86_64',
        'broadwellntbap' => 'x86_64',
        'bromolow' => 'x86_64',
        'cedarview' => 'x86_64',
        'denverton' => 'x86_64',
        'dockerx64' => 'x86_64',
        'epyc7002' => 'x86_64',
        'geminilake' => 'x86_64',
        'grantley' => 'x86_64',
        'kvmx64' => 'x86_64',
        'purley' => 'x86_64',
        'r1000' => 'x86_64',
        'v1000' => 'x86_64',
        'x86' => 'x86_64',
    ];

    /** Alternative spellings of a family that show up in third-party INFO files. */
    public const ALIASES = [
        'x86_64' => ['x64'],
        'armv8' => ['aarch64'],
    ];

    public static function familyOf(string $arch): string
    {
        $arch = self::normalize($arch);
        return self::FAMILIES[$arch] ?? $arch;
    }

    /**
     * Arch tokens a NAS with platform $arch can install, most specific first.
     *
     * @return list<string>
     */
    public static function compatibleWith(string $arch): array
    {
        $arch = self::normalize($arch);
        if ($arch === '88f6282') {
            $arch = '88f6281';
        }
        $family = self::FAMILIES[$arch] ?? $arch;
        $tokens = [$arch, $family, ...(self::ALIASES[$family] ?? []), 'noarch'];
        return array_values(array_unique(array_filter($tokens, static fn (string $t): bool => $t !== '')));
    }

    /**
     * Families with their platforms, for the web UI's platform picker.
     *
     * @return array<string, list<string>>
     */
    public static function grouped(): array
    {
        $groups = [];
        foreach (self::FAMILIES as $arch => $family) {
            $groups[$family][] = $arch;
        }
        ksort($groups);
        foreach ($groups as &$archs) {
            sort($archs);
        }
        unset($archs);
        return $groups;
    }

    public static function normalize(string $arch): string
    {
        return strtolower(trim($arch));
    }
}
