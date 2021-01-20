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
        $this->input->getOption('no-push')->willReturn($input['no-push']);
        $this->package->getExtra()->willReturn(['dbdocker' => $package]);

        $options = new OptionsProvider($this->input->reveal(), $this->package->reveal());

        $this->assertSame($expected['docker-image-name'], $options->getDockerImageName());
        $this->assertSame($expected['docker-tag'], $options->getDockerTag());
        $this->assertSame($expected['git-remote'], $options->getGitRemote());
        $this->assertSame($expected['db-source'], $options->getDbSource());
        $this->assertSame($expected['push'], $options->getPush());
    }

    public function dataOptions(): array
    {
        $cases = [];

        // All defaults
        $cases[] = [
            'input' => [
                'docker-tag' => '',
                'git-remote' => '',
                'db-source' => '',
                'no-push' => false,
            ],
            'package' => [],
            'expected' => [
                'docker-image-name' => 'auto',
                'docker-tag' => 'auto',
                'git-remote' => 'origin',
                'db-source' => '',
                'push' => true,
            ]
        ];

        // Configuration in package extra.
        $cases[] = [
            'input' => [
                'docker-tag' => '',
                'git-remote' => '',
                'db-source' => '',
                'no-push' => false,
            ],
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
            ]
        ];

        // Configuration overridden on CLI.
        $cases[] = [
            'input' => [
                'docker-tag' => 'production',
                'git-remote' => 'upstream',
                'db-source' => '',
                'no-push' => true,
            ],
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
            ]
        ];

        return $cases;
    }
}
