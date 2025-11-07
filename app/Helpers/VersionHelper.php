<?php

namespace App\Helpers;

class VersionHelper
{
    /**
     * Get the current application version from git tag
     *
     * @return string
     */
    public static function getVersion(): string
    {
        // Try to get version from git describe
        try {
            $version = exec('git describe --tags --abbrev=0 2>/dev/null');
            
            if ($version) {
                return $version;
            }
        } catch (\Exception $e) {
            // Git command failed
        }

        // Fallback: try to read from composer.json
        $composerPath = base_path('composer.json');
        if (file_exists($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true);
            if (isset($composer['version'])) {
                return $composer['version'];
            }
        }

        // Final fallback
        return 'dev';
    }

    /**
     * Get framework and version info
     *
     * @return array
     */
    public static function getFrameworkInfo(): array
    {
        return [
            'laravel' => app()->version(),
            'php' => PHP_VERSION,
            'vue' => '3.x',
            'inertia' => 'v2',
        ];
    }

    /**
     * Get all version information
     *
     * @return array
     */
    public static function getAll(): array
    {
        return [
            'version' => self::getVersion(),
            'frameworks' => self::getFrameworkInfo(),
        ];
    }
}
