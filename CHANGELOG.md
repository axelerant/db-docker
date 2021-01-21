# Changelog

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
