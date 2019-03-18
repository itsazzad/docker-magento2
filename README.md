[![Build Status][ico-travis]][link-travis]

# Magento 2 Docker

A collection of Docker images for running Magento 2 through nginx and on the command line.

## Quick Start

    cp .env.sample .env
    # ..you may want to change the default info into .env
    
    cp composer.env.sample composer.env
    # ..put the correct tokens into composer.env

### Don't forget to update `.env` and `composer.env` files

    mkdir magento

    docker-compose run cli magento-installer
    docker-compose up -d
    docker-compose restart

## Docker .env 
Follow the following links  for choosing in between proper php and magento versions and Magento 2.x.x technology stack requirements:

- M2.0.*  https://devdocs.magento.com/guides/v2.0/install-gde/system-requirements-tech.html#php
- M2.1.*  https://devdocs.magento.com/guides/v2.1/install-gde/system-requirements-tech.html#php
- M2.2.*  https://devdocs.magento.com/guides/v2.2/install-gde/system-requirements-tech.html#php
- M2.3.*  https://devdocs.magento.com/guides/v2.3/install-gde/system-requirements-tech.html#php

So some suitable .env settings for `PHP_VERSION` and `MAGENTO_VERSION` can be as follows:

    PHP_VERSION=7.0
    MAGENTO_VERSION=2.0.*
---
    PHP_VERSION=7.1
    MAGENTO_VERSION=2.1.*
---
    PHP_VERSION=7.1
    MAGENTO_VERSION=2.2.*
---
    PHP_VERSION=7.2
    MAGENTO_VERSION=2.3.*
---

## Configuration

Configuration is driven through environment variables.  A comprehensive list of the environment variables used can be found in each `Dockerfile` and the commands in each `bin/` directory.

* `PHP_MEMORY_LIMIT` - The memory limit to be set in the `php.ini`
* `UPLOAD_MAX_FILESIZE` - Upload filesize limit for PHP and Nginx
* `MAGENTO_RUN_MODE` - Valid values, as defined in `Magento\Framework\App\State`: `developer`, `production`, `default`.
* `MAGENTO_ROOT` - The directory to which Magento should be installed (defaults to `/var/www/magento`)
* `COMPOSER_GITHUB_TOKEN` - Your [GitHub OAuth token](https://getcomposer.org/doc/articles/troubleshooting.md#api-rate-limit-and-oauth-tokens), should it be needed
* `COMPOSER_MAGENTO_USERNAME` - Your Magento Connect public authentication key ([how to get](http://devdocs.magento.com/guides/v2.0/install-gde/prereq/connect-auth.html))
* `COMPOSER_MAGENTO_PASSWORD` - Your Magento Connect private authentication key
* `COMPOSER_BITBUCKET_KEY` - Optional - Your Bitbucket OAuth key ([how to get](https://confluence.atlassian.com/bitbucket/oauth-on-bitbucket-cloud-238027431.html))
* `COMPOSER_BITBUCKET_SECRET` - Optional - Your Bitbucket OAuth secret
* `DEBUG` - Toggles tracing in the bash commands when exectued; nothing to do with Magento`
* `PHP_ENABLE_XDEBUG` - When set to `true` it will include the Xdebug ini file as part of the PHP configuration, turning it on. It's recommended to only switch this on when you need it as it will slow down the application.
* `UPDATE_UID_GID` - If this is set to "true" then the uid and gid of `www-data` will be modified in the container to match the values on the mounted folders.  This seems to be necessary to work around virtualbox issues on OSX.

A sample `docker-compose.yml` is provided in this repository.

## CLI Usage

A number of commands are baked into the image and are available on the `$PATH`. These are:

* `magento-command` - Provides a user-safe wrapper around the `bin/magento` command.
* `magento-installer` - Installs and configures Magento into the directory defined in the `$MAGENTO_ROOT` environment variable.
* `magento-extension-installer` - Installs a Magento 2 extension from the `/extensions/<name>` directory, using symlinks.
* `magerun2` - A user-safe wrapper for `n98-magerun2.phar`, which provides a wider range of useful commands. [Learn more here](https://github.com/netz98/n98-magerun2)

It's recommended that you mount an external folder to `/root/.composer/cache`, otherwise you'll be waiting all day for Magento to download every time the container is booted.

CLI commands can be triggered by running:

    docker-compose run cli magento-installer

Shell access to a CLI container can be triggered by running:

    docker-compose run cli bash

## Sendmail

All images have sendmail installed for emails, however it is not enabled by default. To enable sendmail, use the following environment variable:

    ENABLE_SENDMAIL=true

*Note:* If sendmail has been enabled, make sure the container has a hostname assigned using the `hostname` field in `docker-compose.yml` or `--hostname` parameter for `docker run`. If the container does not have a hostname set, sendmail will attempt to discover the hostname on startup, blocking for a prolonged period of time.

## Implementation Notes

* In order to achieve a sane environment for executing commands in, a `docker-environment` script is included as the `ENTRYPOINT` in the container.

## xdebug Usage

To enable xdebug, you will need to toggle the `PHP_ENABLE_XDEBUG` environment variable to `true` in `global.env`. Then when using docker-compose you will need to restart the fpm container using `docker-compose up -d`, or stopping and starting the container.

## Varnish

Varnish is running out of the container by default. If you do not require varnish, then you will need to remove the varnish block from your `docker-compose.yml` and uncomment the `environment` section under the `web` container definition.

To clear varnish, you can use the `cli` containers `magento-command` to clear the cache, which will include varnish. Alternatively, you could restart the varnish container.

    docker-compose run --rm cli magento-command cache:flush
    # OR
    docker-compose restart varnish

If you need to add your own VCL, then it needs to be mounted to: `/data/varnish.vcl`.

## Building

A lot of the configuration for each image is the same, with the difference being the base image that they're extending from.  For this reason we use `php` to build the `Dockerfile` from a set of templates in `src/`.  The `Dockerfile` should still be published to the repository due to Docker Hub needing a `Dockerfile` to build from.

To build all `Dockerfile`s, run the `builder.php` script in the `php:7` Docker image:<!-- Yo dawg, I heard you like Docker images... -->

    docker run --rm -it -v $(pwd):/src php:7 php /src/builder.php

### Adding new images to the build config

The build configuration is controlled by the `config.json` file. Yeah element in the top level hash is a new build target, using the following syntax:

    "<target-name>": {
        "version": "<php-version>",
        "flavour": "<image-flavour>",
        "files": {
            "<target-file-name>": {
                "<template-variable-name>": "<template-variable-value>",
                ...
            },
    }

The target files will be rendered in the `<php-version>-<image-flavour>/` directory.

The source template for each target file is selected from the `src/` directory using the following fallback order:

1. `<target-file-name>-<php-version>-<image-flavour>`
2. `<target-file-name>-<php-version>`
3. `<target-file-name>-<image-flavour>`
4. `<target-file-name>`

Individual templates may include other templates as partials.


# Web Browsing Usage
Lists containers:

    docker ps
    
Output example:
    
    CONTAINER ID        IMAGE                              COMMAND                  CREATED             STATUS              PORTS                                           NAMES

    07387a659081        docker-magento2_web       "/usr/local/bin/dock…"   15 minutes ago      Up 6 minutes        0.0.0.0:32793->80/tcp, 0.0.0.0:32792->443/tcp   docker-magento2_web_1_6d400cbbcbbc

## Way 1
Simplest way is to find the container ip address:

    docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' <CONTAINER ID or NAME>
    
And then edit the /etc/hosts file:
    
    sudo nano /etc/hosts
    
Set in the end set like as:

    <IPAddress> magento2.docker
    
- http://magento2.docker

- https://magento2.docker
    
## Way 2
Check in the `Ports` column for ports mapping.

Example:
    
    0.0.0.0:32793->80/tcp, 0.0.0.0:32792->443/tcp

In that case:

    docker-compose run --rm cli magento-command setup:store-config:set --base-url="http://magento2.docker:32783/"
    docker-compose run --rm cli magento-command setup:store-config:set --base-url-secure="https://magento2.docker:32782/"

- http://magento2.docker:32783

- https://magento2.docker:32782


## Troubleshoot
- Reset application:


    docker-compose down --volumes --rmi all
    rm -rf magento
    rm -rf logs


[ico-travis]: https://img.shields.io/travis/itsazzad/docker-magento2.svg?style=flat-square
[link-travis]: https://travis-ci.org/itsazzad/docker-magento2
