# Changelog

## UNRELEASED

* Add support for MariaDB 10.6 for DDEV

## v1.1.2

* List ddev as a db-source by @skippednote in #21
* Add support for GitHub URLs by @skippednote in #23

## v1.1.1

* Set db-source to file when db-file option is present. Fixes #17.

## v1.1.0

* Update documentation for DDEV support
* Fix #15: Add support for DDEV
* Remove an invalid SQL statement in truncate caches file
* Fix the ARG order in Dockerfile

## v1.0.1

* Add support for symfony/process 2.8. Fixes #12

## v1.0.0

* Set a high timeout value so that docker build and push have enough time
* Add documentation for docker-base section

## v1.0.0-beta10

* Warn if the image is not something we recognize. Fixes #11
* Add support for specifying a base image. Fixes #11
* Add a partial package config test

## v1.0.0-beta9

* Fix issue with the flipped behaviour of --no-push parameter

## v1.0.0-beta8

* Add support for any Docker registry. Fixes #10
* Merge pull request #9 from axelerant/options-extra
* Fix hash parameters for GH Actions caching
* Add phpunit to Github Actions
* Add tests for OptionsProvider
* Document how to configure the plugin using the `extra` section
* Support configuration from composer.json extra section. Fixes #8
* Clarify the usage instructions in README
* Fixes #5: Do not query remotes when reading information
* Merge pull request #2 from axelerant/phplint
* Add php lint and cs workflow
* Add phplint
* Use symfony/process for running commands
* Reformat code to take lesser space
* Fix comment in DbDocker
* Enforce PSR-12 standards
* Update CHANGELOG for beta7 release

## v1.0.0-beta7

* Correctly determine paths when building images

## v1.0.0-beta6

* Minor improvements
* Remove a TODO after testing
* Move the dockerize-db Dockerfile and SQL inside the plugin

## v1.0.0-beta5

* Remove shortcut for no-push

## v1.0.0-beta4

* Add support for composer 2.0
* Merge pull request #1 from axelerant/arguments
* Update single hyphen arugment name
