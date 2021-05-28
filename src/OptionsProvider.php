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
        $this->packageConfig = $extra['dbdocker'] ?? [];
        $this->packageConfig += [
            'docker-image-name' => 'auto',
            'docker-tag' => 'auto',
            'docker-base' => [],
            'git-remote' => '',
            'db-source' => '',
            'no-push' => false,
        ];

        $baseImageDetailsDefault = [
            'base-flavor' => 'bitnami',
            'image' => 'bitnami/mariadb:10.4',
            'user' => 'drupal8',
            'password' => 'drupal8',
            'database' => 'drupal8',
        ];
        $baseFlavor = $this->packageConfig['docker-base']['base-flavor'] ?? 'default';
        if ($baseFlavor == 'ddev') {
            $baseImageDetailsDefault = [
                'image' => 'drud/ddev-dbserver-mariadb-10.4:v1.17.0',
                'user' => 'db',
                'password' => 'db',
                'database' => 'db',
            ];
        }
        $this->packageConfig['docker-base'] += $baseImageDetailsDefault;
    }

    public function getDockerTag(): string
    {
        return $this->input->getOption('docker-tag') ?: $this->packageConfig['docker-tag'];
    }

    public function getDockerImageName(): string
    {
        return $this->packageConfig['docker-image-name'];
    }

    public function getDockerBaseDetails(): array
    {
        return $this->packageConfig['docker-base'];
    }

    public function getGitRemote(): string
    {
        return $this->input->getOption('git-remote') ?: $this->packageConfig['git-remote'] ?: 'origin';
    }

    public function getDbSource(): string
    {
        $dbSource = $this->input->getOption('db-source') ?: $this->packageConfig['db-source'];
        // If the db-file option is set, always set db-source to file.
        // See https://github.com/axelerant/db-docker/issues/17.
        if ($this->input->getOption('db-file')) {
            $dbSource = 'file';
        }

        return $dbSource;
    }

    public function getPush(): bool
    {
        return !($this->input->getOption('no-push') ?: $this->packageConfig['no-push']);
    }
}
