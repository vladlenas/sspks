<?php

declare(strict_types=1);

namespace SSpkS;

final class PackageFilter
{
    /**
     * Packages a NAS can install: right platform, right DSM generation,
     * minimum DSM version met, beta only on the beta channel. One entry
     * per package name, the newest version (most specific build on a tie).
     *
     * @param list<Package> $packages
     * @param string $firmware DSM version as "major.minor.build".
     * @return list<Package>
     */
    public static function forDevice(array $packages, string $arch, string $firmware, string $channel): array
    {
        $tokens = Architectures::compatibleWith($arch);
        $deviceIsDsm7 = version_compare($firmware, '7', '>=');

        $best = [];
        $bestMatch = [];
        foreach ($packages as $package) {
            $match = $package->archMatch($tokens);
            if ($match === null
                || $package->isForDsm7() !== $deviceIsDsm7
                || version_compare($package->minDsm(), $firmware, '>')
                || ($package->isBeta() && $channel !== 'beta')
            ) {
                continue;
            }

            $name = $package->name();
            if (!isset($best[$name])) {
                $best[$name] = $package;
                $bestMatch[$name] = $match;
                continue;
            }
            $cmp = version_compare($package->version(), $best[$name]->version());
            if ($cmp > 0 || ($cmp === 0 && $match < $bestMatch[$name])) {
                $best[$name] = $package;
                $bestMatch[$name] = $match;
            }
        }

        return array_values($best);
    }
}
