<?php

namespace Axelerant\DbDocker;

use Composer\Command\BaseCommand;
use GitElephant\Repository;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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

    protected function configure()
    {
        $this->setName('db-docker')
            ->setDescription('Generate a Docker image for the database.')
            ->addOption('docker-tag', 't', InputOption::VALUE_OPTIONAL,
                'The Docker tag to build')
            ->addOption('git-remote', 'r', InputOption::VALUE_OPTIONAL,
                'The git remote to use to determine the image name', 'origin')
            ->addOption('db-source', 's', InputOption::VALUE_OPTIONAL,
                'Source of the database ("lando", "drush", or "file")')
            ->addOption('db-file', 'f', InputOption::VALUE_OPTIONAL,
                'The path to the database file (required if db-source is set to file)')
            ->addOption('no-push', NULL, InputOption::VALUE_NONE,
                'Set to not push the image after building');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        // Determine the Docker image name.
        $imageId = $this->getImageId();

        // Get the database file.
        $sqlFile = $this->getDbFile();

        // Clone the dockerize-db repository so that we can build the image
        $this->buildImage($imageId, $sqlFile);

        if (!$this->input->getOption('no-push')) {
            $this->output->writeln("<info>Pushing image...</info>");
            $this->execCmd("docker push " . $imageId);
        }
        else {
            $this->output->writeln(sprintf("<info>To push image, run '%s'</info>", "docker push " . $imageId));
        }
    }

    protected function getImageId(): string
    {
        // TODO: See if this works in a subdirectory and if not, implement.
        $git = new Repository(getcwd());
        $tag = $this->input->getOption('docker-tag');
        if (!$tag) {
            $tag = $git->getMainBranch()->getName();
            $this->output->writeln(sprintf("<info>Docker tag not specified. Using current branch name: %s</info>",
                $tag));

            // We should be using the tag 'latest' if the current branch is 'master'.
            if ($tag == 'master') {
                $tag = 'latest';
                $this->output->writeln("<info>Using Docker tag 'latest' for branch 'master'.</info>",
                    OutputInterface::VERBOSITY_VERBOSE);
            }
        }

        // Throws an exception if the remote not found, so we don't have to.
        $remote = $git->getRemote($this->input->getOption('git-remote'));

        // Determine the image name (path) from the git remote URL.
        $imageId = $this->getImagePathFromRepoUrl($remote->getFetchURL(), $tag);
        return $imageId;
    }

    protected function getImagePathFromRepoUrl(string $url, string $tag): string
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
            default:
                throw new InvalidOptionException("The specified git remote URL isn't supported");
        }

        return sprintf("%s/%s/db:%s", $registryDomain, strtolower($path), $tag);
    }

    protected function getDbFile(): string
    {
        $src = $this->input->getOption('db-source') ?: $this->guessSource();
        if ($src != 'lando' && $src != 'drush' && $src != 'file') {
            throw new InvalidOptionException("db-source can only be 'lando', 'drush', or 'file'");
        }

        $this->output->writeln(sprintf("<info>Getting SQL file from source '%s'</info>",
            $src));

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

        $this->execCmd($drushCmd);
        return $sqlFileName;
    }

    protected function guessSource(): string
    {
        if ($this->input->getOption('db-file')) {
            return 'file';
        }

        // If there is a file called '.lando.yml', there is a good chance
        // we should s
        if (file_exists('.lando.yml')) {
            // If we are running inside Lando, just use 'drush'.
            return getenv('LANDO') == 'ON' ? 'drush' : 'lando';
        }

        return 'drush';
    }

    /**
     * @param string $imageId
     * @param string $sqlFile
     *
     * @return string
     */
    protected function buildImage(string $imageId, string $sqlFile): void
    {
        $repo = Repository::createFromRemote('git@gitlab.axl8.xyz:tooling/dockerize-db.git');
        $repo_path = $repo->getPath();
        copy($sqlFile, $repo_path . "/dumps/db.sql");

        $this->output->writeln(sprintf("<info>Building image '%s'</info>",
            $imageId));
        $dockerCmd = sprintf("docker build -t %s %s", $imageId, $repo_path);
        $this->execCmd($dockerCmd);
    }

    protected function execCmd($cmd): void
    {
        $this->output->writeln(sprintf("<info>Running '%s'</info>", $cmd),
            OutputInterface::VERBOSITY_VERBOSE);
        exec($cmd, $res, $code);
        if ($code != 0) {
            $this->output->writeln(sprintf("<error>Command returned exit code '%d'</error>",
                $code));
        }
        $this->output->writeln($res,
            OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_VERBOSE);

        if ($code != 0) {
            $msg = sprintf("Command returned exit code '%d'\n%s",
                $code, implode("\n", $res));
            throw new RuntimeException($msg, $code);
        }
    }
}
