<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function getCacheDir(): string
    {
        return $this->resolveRuntimePath('cache/' . $this->environment);
    }

    public function getBuildDir(): string
    {
        return $this->resolveRuntimePath('cache/' . $this->environment);
    }

    public function getLogDir(): string
    {
        return $this->resolveRuntimePath('log');
    }

    private function resolveRuntimePath(string $suffix): string
    {
        $suffix = trim(str_replace('\\', '/', $suffix), '/');
        $projectVarDir = $this->getProjectDir() . '/var';

        if ($this->ensureWritableDirectory($projectVarDir)) {
            return $projectVarDir . ($suffix !== '' ? '/' . $suffix : '');
        }

        $fallbackBaseDir = $this->getFallbackRuntimeBaseDir();

        return $fallbackBaseDir . ($suffix !== '' ? '/' . $suffix : '');
    }

    private function getFallbackRuntimeBaseDir(): string
    {
        $candidates = [];
        $homeDir = trim((string) ($_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? ''));
        $tempDir = trim((string) ($_SERVER['TMPDIR'] ?? sys_get_temp_dir()));
        $projectHash = substr(sha1($this->getProjectDir()), 0, 12);

        if ($homeDir !== '') {
            $candidates[] = rtrim(str_replace('\\', '/', $homeDir), '/') . '/tmp/dashboard-runtime-' . $projectHash;
        }

        if ($tempDir !== '') {
            $candidates[] = rtrim(str_replace('\\', '/', $tempDir), '/') . '/dashboard-runtime-' . $projectHash;
        }

        foreach ($candidates as $candidate) {
            if ($this->ensureWritableDirectory($candidate)) {
                return $candidate;
            }
        }

        return $projectVarDir = $this->getProjectDir() . '/var';
    }

    private function ensureWritableDirectory(string $directory): bool
    {
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return false;
        }

        return is_writable($directory);
    }
}
