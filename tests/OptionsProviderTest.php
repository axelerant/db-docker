<?php

namespace Axelerant\DbDocker\Tests;

use Axelerant\DbDocker\OptionsProvider;
use Composer\Package\RootPackageInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophet;
use Symfony\Component\Console\Input\InputInterface;

class OptionsProviderTest extends TestCase
{

    protected $prophet;

    protected $input;

    protected $package;

    public function setUp(): void
    {
        parent::setUp();
        $this->prophet = new Prophet();
        $this->input = $this->prophet->prophesize(InputInterface::class);
        $this->package = $this->prophet->prophesize(RootPackageInterface::class);
    }

    /**
     * @dataProvider dataOptions
     */
    public function testRegularOptions($input, $package, $expected): void
    {
        $this->input->getOption('docker-tag')->willReturn($input['docker-tag']);
        $this->input->getOption('git-remote')->willReturn($input['git-remote']);
        $this->input->getOption('db-source')->willReturn($input['db-source']);
        $this->input->getOption('db-file')->willReturn($input['db-file']);
        $this->input->getOption('no-push')->willReturn($input['no-push']);
        $this->package->getExtra()->willReturn(['dbdocker' => $package]);

        $options = new OptionsProvider($this->input->reveal(), $this->package->reveal());

        $this->assertSame($expected['docker-image-name'], $options->getDockerImageName());
        $this->assertSame($expected['docker-tag'], $options->getDockerTag());
        $this->assertSame($expected['git-remote'], $options->getGitRemote());
        $this->assertSame($expected['db-source'], $options->getDbSource());
        $this->assertSame($expected['push'], $options->getPush());

        $base = $options->getDockerBaseDetails();
        $this->assertSame($expected['docker-base']['image'], $base['image']);
        $this->assertSame($expected['docker-base']['user'], $base['user']);
        $this->assertSame($expected['docker-base']['password'], $base['password']);
        $this->assertSame($expected['docker-base']['database'], $base['database']);
    }

    public function dataOptions(): array
    {
        $defaultInput = [
            'docker-tag' => '',
            'git-remote' => '',
            'db-source' => '',
            'db-file' => '',
            'no-push' => false,
        ];
        $defaultExpected = [
            'docker-image-name' => 'auto',
            'docker-tag' => 'auto',
            'docker-base' => [
                'base-flavor' => 'bitnami',
                'image' => 'bitnami/mariadb:10.4',
                'user' => 'drupal8',
                'password' => 'drupal8',
                'database' => 'drupal8',
            ],
            'git-remote' => 'origin',
            'db-source' => '',
            'push' => true,
        ];

        $cases = [];

        // All defaults
        $cases[] = [
            'input' => $defaultInput,
            'package' => [],
            'expected' => $defaultExpected,
        ];

        // Configuration in package extra.
        $cases[] = [
            'input' => $defaultInput,
            'package' => [
                'docker-image-name' => 'test/image',
                'docker-tag' => 'latest',
                'git-remote' => 'upstream',
                'db-source' => 'lando',
                'no-push' => false,
            ],
            'expected' => [
                'docker-image-name' => 'test/image',
                'docker-tag' => 'latest',
                'git-remote' => 'upstream',
                'db-source' => 'lando',
                'push' => true,
            ] + $defaultExpected,
        ];

        // Configuration overridden on CLI.
        $cases[] = [
            'input' => [
                'docker-tag' => 'production',
                'git-remote' => 'upstream',
                'db-source' => '',
                'no-push' => true,
            ] + $defaultInput,
            'package' => [
                'docker-image-name' => 'auto',
                'docker-tag' => 'latest',
                'git-remote' => '',
                'db-source' => 'lando',
                'no-push' => false,
            ],
            'expected' => [
                'docker-image-name' => 'auto',
                'docker-tag' => 'production',
                'git-remote' => 'upstream',
                'db-source' => 'lando',
                'push' => false,
            ] + $defaultExpected,
        ];

        // Partial configuration in composer.json.
        $cases[] = [
            'input' => [
                'docker-tag' => 'production',
                'git-remote' => 'upstream',
                'db-source' => '',
                'no-push' => true,
            ] + $defaultInput,
            'package' => [
                'docker-tag' => 'latest',
                'db-source' => 'lando',
            ],
            'expected' => [
                'docker-image-name' => 'auto',
                'docker-tag' => 'production',
                'git-remote' => 'upstream',
                'db-source' => 'lando',
                'push' => false,
            ] + $defaultExpected,
        ];

        // Input of db-file should always set the db-source option to 'file'.
        // See https://github.com/axelerant/db-docker/issues/17.
        $cases[] = [
            'input' => [
                'db-source' => '',
                'db-file' => 'file.sql',
            ] + $defaultInput,
            'package' => [
                'db-source' => 'lando',
            ],
            'expected' => [
                'db-source' => 'file',
            ] + $defaultExpected,
        ];

        // Configuration with docker-base.
        $cases[] = [
            'input' => $defaultInput,
            'package' => [
                'docker-base' => [
                    'image' => 'mariadb:latest',
                    'user' => 'mysql',
                    'password' => 'root',
                    'database' => 'drupal',
                ],
            ],
            'expected' => [
                'docker-base' => [
                    'image' => 'mariadb:latest',
                    'user' => 'mysql',
                    'password' => 'root',
                    'database' => 'drupal',
                ],
            ] + $defaultExpected,
        ];

        // Partial configuration with docker-base.
        $cases[] = [
            'input' => $defaultInput,
            'package' => [
                'docker-tag' => 'latest',
                'docker-base' => [
                    'image' => 'mariadb:latest',
                ],
                'db-source' => 'lando',
            ],
            'expected' => [
                    'docker-tag' => 'latest',
                    'docker-base' => [
                        'image' => 'mariadb:latest',
                        'user' => 'drupal8',
                        'password' => 'drupal8',
                        'database' => 'drupal8',
                    ],
                    'db-source' => 'lando',
                ] + $defaultExpected,
        ];

        // Partial configuration with DDEV base flavor.
        $cases[] = [
            'input' => $defaultInput,
            'package' => [
                'docker-tag' => 'latest',
                'docker-base' => [
                    'base-flavor' => 'ddev',
                ],
                'db-source' => 'lando',
            ],
            'expected' => [
                    'docker-tag' => 'latest',
                    'docker-base' => [
                        'base-flavor' => 'ddev',
                        'image' => 'drud/ddev-dbserver-mariadb-10.4:v1.17.0',
                        'user' => 'db',
                        'password' => 'db',
                        'database' => 'db',
                    ],
                    'db-source' => 'lando',
                ] + $defaultExpected,
        ];

        return $cases;
    }
}
