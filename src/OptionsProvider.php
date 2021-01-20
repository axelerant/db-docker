<?php

namespace Axelerant\DbDocker;

use Composer\Package\RootPackageInterface;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Class OptionsProvider
 * @package Axelerant\DbDocker
 *
 * Get options first from the command line input and if not available, from the
 * configuration in composer.json file.
 */
class OptionsProvider
{

    /**
     * @var InputInterface
     */
    protected $input;
    /**
     * @var array
     */
    protected $packageConfig;

    public function __construct(InputInterface $input, RootPackageInterface $package)
    {
        $this->input = $input;
        $extra = $package->getExtra();
        $this->packageConfig = ($extra['dbdocker'] ?? []) + [
            'docker-image-name' => 'auto',
            'docker-tag' => 'auto',
            'git-remote' => '',
            'db-source' => '',
            'no-push' => false,
        ];
    }

    public function getDockerTag(): string
    {
        return $this->input->getOption('docker-tag') ?: $this->packageConfig['docker-tag'];
    }

    public function getDockerImageName(): string
    {
        return $this->packageConfig['docker-image-name'];
    }

    public function getGitRemote(): string
    {
        return $this->input->getOption('git-remote') ?: $this->packageConfig['git-remote'] ?: 'origin';
    }

    public function getDbSource(): string
    {
        $dbSource = $this->input->getOption('db-source') ?: $this->packageConfig['db-source'];
        // Guess the database source if it is not specified.
        if (!$dbSource && $this->input->getOption('db-file')) {
            $dbSource = 'file';
        }

        return $dbSource;
    }

    public function getPush(): bool
    {
        return !($this->input->getOption('no-push') ?: $this->packageConfig['no-push']);
    }
}
