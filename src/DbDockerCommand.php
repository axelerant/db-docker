<?php

namespace Axelerant\DbDocker;

use Composer\Command\BaseCommand;
use GitElephant\Repository;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

class DbDockerCommand extends BaseCommand
{
    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var OptionsProvider
     */
    protected $options;

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->setName('db-docker')
            ->setDescription('Generate a Docker image for the database.')
            ->addOption(
                'docker-tag',
                't',
                InputOption::VALUE_OPTIONAL,
                'The Docker tag to build'
            )
            ->addOption(
                'git-remote',
                'r',
                InputOption::VALUE_OPTIONAL,
                'The git remote to use to determine the image name'
            )
            ->addOption(
                'db-source',
                's',
                InputOption::VALUE_OPTIONAL,
                'Source of the database ("lando", "ddev", "drush", or "file")'
            )
            ->addOption(
                'db-file',
                'f',
                InputOption::VALUE_OPTIONAL,
                'The path to the database file (required if db-source is set to file)'
            )
            ->addOption(
                'no-push',
                null,
                InputOption::VALUE_NONE,
                'Set to not push the image after building'
            );
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->options = new OptionsProvider($input, $this->getComposer()->getPackage());

        $imageId = $this->getImageId();

        $sqlFile = $this->getDbFile();

        $baseDetails = $this->options->getDockerBaseDetails();
        $this->verifyBaseImage($baseDetails);
        $this->buildImage($imageId, $sqlFile, $baseDetails);

        if ($this->options->getPush()) {
            $this->output->writeln("<info>Pushing image...</info>");
            $this->execCmd(['docker', 'push', $imageId]);
        } else {
            $this->output->writeln(sprintf("<info>To push image, run '%s'</info>", "docker push " . $imageId));
        }

        return 0;
    }

    /**
     * Get the complete image name (with tag) based on given options.
     */
    protected function getImageId(): string
    {
        // We can safely use `getcwd()` even in a subdirectory.
        $git = new Repository(getcwd());
        $tag = $this->options->getDockerTag();
        if (!$tag || $tag == 'auto') {
            $tag = $git->getMainBranch()->getName();
            $this->output->writeln("<info>Docker tag not specified. Using current branch name: {$tag}</info>");

            // We should be using the tag 'latest' if the current branch is 'master' or 'main'.
            if ($tag == 'master' || $tag == 'main') {
                $tag = 'latest';
                $this->output->writeln(
                    "<info>Using Docker tag 'latest' for branch '{$tag}'.</info>",
                    OutputInterface::VERBOSITY_VERBOSE
                );
            }
        }

        $imageName = $this->options->getDockerImageName();
        if (!$imageName || $imageName == "auto") {
            // Throws an exception if the remote not found, so we don't have to.
            $remote = $git->getRemote($this->options->getGitRemote(), false);
            $imageName = $this->getImageNameFromRepoUrl($remote->getFetchURL());
            $this->output->writeln("<info>Docker image not specified. Using from git repository: {$imageName}</info>");
        }

        // Determine the image name (path) from the git remote URL.
        return sprintf("%s:%s", $imageName, $tag);
    }

    /**
     * Determine the Docker image name from the repo URL.
     *
     * @param string $url
     *
     * @return string
     */
    protected function getImageNameFromRepoUrl(string $url): string
    {
        if (!preg_match('/^[^@]*@([^:]*):(.*)\.git$/', $url, $matches)) {
            throw new InvalidOptionException("The specified git remote URL couldn't be parsed");
        }

        $host = $matches[1];
        $path = $matches[2];
        switch ($host) {
            case 'gitlab.axl8.xyz':
                $registryDomain = 'registry.axl8.xyz';
                break;
            case 'gitorious.xyz':
            case 'code.axelerant.com':
                $registryDomain = 'registry.gitorious.xyz';
                break;
            case 'github.com':
                $registryDomain = 'ghcr.io';
                break;
            default:
                throw new InvalidOptionException("The specified git remote URL isn't supported");
        }

        return sprintf("%s/%s/db", $registryDomain, strtolower($path));
    }

    /**
     * Get the database file path based on the source.
     */
    protected function getDbFile(): string
    {
        $src = $this->options->getDbSource() ?: $this->guessSource();
        $validOptions = ['lando', 'ddev', 'drush', 'file'];
        if (!in_array($src, $validOptions)) {
            throw new InvalidOptionException("db-source can only be one of 'lando', 'ddev', 'drush', or 'file'");
        }

        $this->output->writeln("<info>Getting SQL file from source '{$src}'</info>");

        if ($src == 'file') {
            if (!$this->input->getOption('db-file')) {
                throw new InvalidOptionException("db-file is required if db-source is set to 'file'");
            }
            return realpath($this->input->getOption('db-file'));
        }

        // Get SQL from Lando or Drush.
        $sqlFileName = tempnam(sys_get_temp_dir(), 'axldb');
        $drushCmd = 'drush sql:dump > ' . $sqlFileName;
        if ($src == 'lando') {
            $drushCmd = 'lando ' . $drushCmd;
        }
        if ($src == 'ddev') {
            $drushCmd = 'ddev ' . $drushCmd;
        }

        $this->execCmd($drushCmd);
        return $sqlFileName;
    }

    /**
     * Guess the best database source.
     */
    protected function guessSource(): string
    {
        // If there is a file called '.lando.yml', there is a good chance
        // that the project uses lando and we should use that for the source.
        if (file_exists('.lando.yml')) {
            // If we are running inside Lando, just use 'drush'.
            return getenv('LANDO') == 'ON' ? 'drush' : 'lando';
        }
        // Similarly, if there is a directory called `.ddev`, we are probably
        // running ddev.
        if (file_exists('.ddev')) {
            // If we are running inside DDEV, just use 'drush'.
            return getenv('IS_DDEV_PROJECT') ? 'drush' : 'ddev';
        }

        return 'drush';
    }

    /**
     * Show warnings if the image doesn't appear to be a MariaDB image.
     *
     * @param array $baseImageDetails
     */
    protected function verifyBaseImage(array $baseImageDetails): void
    {
        $image = $baseImageDetails['image'];
        if (!preg_match("/^([^:]+)(:.+)?$/", $image, $matches)) {
            throw new \UnexpectedValueException("Cannot parse Docker image name: '{$image}.'");
        }

        $imageName = $matches[1];
        $allowedImages = [
            'bitnami/mariadb',
            'mariadb',
            'drud/ddev-dbserver-mariadb-10.2',
            'drud/ddev-dbserver-mariadb-10.3',
            'drud/ddev-dbserver-mariadb-10.4',
            'drud/ddev-dbserver-mariadb-10.5',
            'drud/ddev-dbserver-mariadb-10.6',
        ];
        if (!in_array(strtolower($imageName), $allowedImages)) {
            $this->output->writeln(
                "<comment>Cannot recognize image name '{$imageName}'. Use at your own risk.</comment>"
            );
        }
    }

    /**
     * Build the image using our Dockerfile and SQL scripts
     *
     * @param string $imageId
     * @param string $sqlFile
     * @param array $baseDetails
     */
    protected function buildImage(string $imageId, string $sqlFile, array $baseDetails): void
    {
        $tempDir = realpath(sys_get_temp_dir());
        $tempPath = sprintf('%s%s%s', $tempDir, DIRECTORY_SEPARATOR, sha1(uniqid())) . '/';

        $dockerfileName = $baseDetails['base-flavor'] == 'ddev' ? 'dockerize-db-ddev' : 'dockerize-db';
        $assetPath = realpath(__DIR__ . '/../assets/' . $dockerfileName) . '/';

        mkdir($tempPath);
        mkdir($tempPath . 'dumps');
        copy($assetPath . 'Dockerfile', $tempPath . 'Dockerfile');
        copy($sqlFile, $tempPath . "/dumps/db.sql");
        copy($assetPath . "zzzz-truncate-caches.sql", $tempPath . "zzzz-truncate-caches.sql");

        // Little hacky but this works for now
        if (file_exists($assetPath . 'create_init_db.sh')) {
            copy($assetPath . 'create_init_db.sh', $tempPath . 'create_init_db.sh');
            chmod($tempPath . 'create_init_db.sh', 0755);
        }

        $this->output->writeln("<info>Building image '{$imageId}'</info>");
        $dockerCmd = ['docker', 'build', '-t', $imageId];
        $dockerCmd[] = '--build-arg';
        $dockerCmd[] = sprintf("BASE_IMAGE=%s", $baseDetails['image']);
        $dockerCmd[] = '--build-arg';
        $dockerCmd[] = sprintf("BASE_IMAGE_USER=%s", $baseDetails['user']);
        $dockerCmd[] = '--build-arg';
        $dockerCmd[] = sprintf("BASE_IMAGE_PASSWORD=%s", $baseDetails['password']);
        $dockerCmd[] = '--build-arg';
        $dockerCmd[] = sprintf("BASE_IMAGE_DATABASE=%s", $baseDetails['database']);
        $dockerCmd[] = $tempPath;
        $this->execCmd($dockerCmd);
    }

    protected function execCmd($cmd): void
    {
        $this->output->writeln(sprintf(
            "<info>Running '%s'</info>",
            is_array($cmd) ? implode(" ", $cmd) : $cmd
        ), OutputInterface::VERBOSITY_VERBOSE);

        if (is_array($cmd) && PHP_VERSION_ID < 70400 && class_exists(ProcessBuilder::class)) {
            // Composer embeds an old version of symfony/process, and we need to
            // target that. This version (2.8.52 as of this writing) does not
            // support command line as an array which results in an error with
            // proc_open before PHP 7.4. This is the case we should target.
            // See https://github.com/axelerant/db-docker/issues/12.
            $p = (new ProcessBuilder($cmd))->getProcess();
        } elseif (is_string($cmd) && method_exists(Process::class, 'fromShellCommandline')) {
            // BC for symfony/process < 4.2.
            // The method fromShellCommandline is new in 4.2 and it deprecated
            // using strings for constructor (and was removed in 5). Since we are
            // trying to support a variety of versions, this check is necessary when
            // the command is a string.
            // This code is only left here in case composer updates its version of
            // symfony/process one day. In most cases, this line of code will not
            // be executed when using composer phar file.
            $p = Process::fromShellCommandline($cmd);
        } else {
            // Versions of symfony/process from 3.3 to before 5.0 supported
            // constructor with string AND array parameters.
            $p = new Process($cmd);
        }

        $p->setTimeout(600);
        $code = $p->run();
        $this->output->writeln($p->getOutput(), OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_VERBOSE);

        if (!$p->isSuccessful()) {
            $this->output->writeln("<error>Command returned exit code '{$code}'</error>");
            throw new ProcessFailedException($p);
        }
    }
}
