# This image is used to copy and install the databases.
ARG BASE_IMAGE=bitnami/mariadb:10.4
ARG BASE_IMAGE_USER
ARG BASE_IMAGE_PASSWORD
ARG BASE_IMAGE_DATABASE
FROM ${BASE_IMAGE} AS build

ENV ALLOW_EMPTY_PASSWORD=yes
ENV MARIADB_USER=${BASE_IMAGE_USER:-drupal8}
ENV MARIADB_PASSWORD=${BASE_IMAGE_PASSWORD:-drupal8}
ENV MARIADB_DATABASE=${BASE_IMAGE_DATABASE:-drupal8}

COPY dumps/ /docker-entrypoint-initdb.d/
COPY zzzz-truncate-caches.sql /docker-entrypoint-initdb.d/

RUN /opt/bitnami/scripts/mariadb/setup.sh

# This image is used to copy the installed databases and configure MySQL.
FROM ${BASE_IMAGE}

COPY --from=build --chown=1001 /bitnami/mariadb /bitnami/mariadb
