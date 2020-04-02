# db-docker
Generate a Database as an image

## Introduction

This is a tool for Axelerant specific processes. As such, you wouldn't be able to use this plugin if you cannot access Axelerant's GitLab repository.

## Prerequisites

* Reasonably updated version of composer with recent version of PHP. Tested with composer 1.10.1.
* Recent version of Docker.
* Ability to clone from gitlab.axl8.xyz.
* Logged in to Axelerant's GitLab Container Registry. To verify, run `docker login registry.axl8.xyz`. Optional if you use the `--no-push` option.

## Installation

Install with composer into any Drupal project.

```bash
composer require --dev axelerant/db-docker
```

## Usage

```
Usage:
  db-docker [options]

Options:
      --no-push                       Set to not push the image after building
  -h, --help                          Display this help message
  -q, --quiet                         Do not output any message
  -V, --version                       Display this application version
      --ansi                          Force ANSI output
      --no-ansi                       Disable ANSI output
  -n, --no-interaction                Do not ask any interactive question
      --profile                       Display timing and memory usage information
      --no-plugins                    Whether to disable plugins.
  -d, --working-dir=WORKING-DIR       If specified, use the given directory as working directory.
      --no-cache                      Prevent use of the cache
  -tag, --docker-tag[=DOCKER-TAG]     The Docker tag to build
  -remote, --git-remote[=GIT-REMOTE]  The git remote to use to determine the image name [default: "origin"]
  -src, --db-source[=DB-SOURCE]       Source of the database ("lando", "drush", or "file")
  -file, --db-file[=DB-FILE]          The path to the database file (required if db-source is set to file)
  -v|vv|vvv, --verbose                Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Help:
  Generate a Docker image for the database.
```

### Examples

To let the plugin guess defaults, build the image, and push it.

```bash
composer db-docker
```

To explicitly specify a SQL file to build the image.

```bash
composer db-docker --db-source=file --db-file=<filename> # The file can either be plain SQL or gzipped.
```

## Default options

The plugin tries to guess most values for input to correctly select the source, build the image, and push it.

### Determining the image name

The image name is determined based on the git repository's `origin` remote (overridable using the `--git-remote` option). The remote URL should be a Git URL (not a HTTP URL) of type `git@gitlab.axl8.xyz:<group>/<project>.git`. For this, it would determine the image name `registry.axl8.xyz/<group>/<project>/db`. See the next section for the image tag.

### Determining the image tag

The image tag, unless specified with the `--docker-tag` option, is assumed to be the current branch name. If the current branch is `master`, the image tag is used as `latest`.

### Determining the database source

Three database sources are supported: `file`, `lando`, and `drush`. The source can be explicitly specified using the `--db-source` option. If not specified, the following rules are used to determine the source.
* If the `--db-file` option is present, then the source is set as `file`.
* If a file called `.lando.yml` is present, then the source is set as `lando`.
  * As an exception to above, the plugin attempts to detect if it is running inside a lando container. If so, the source is set to `drush`.
* If the above two conditions fail, then the source is assumed to be `drush`.

In case the source is `lando` or `drush`, the `drush sql:dump` command is used to obtain the SQL file. If the source is `lando`, then the drush command is executed inside of the Lando container like so: `lando drush ...`.

## Reporting problems

If you see a bug or an improvement, create a pull request. For support, raise a request on Axelerant Slack #internal-support channel.
