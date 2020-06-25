# This image is used to copy and install the databases.
FROM bitnami/mariadb:10.4 AS build

ENV ALLOW_EMPTY_PASSWORD=yes
ENV MARIADB_USER=drupal8
ENV MARIADB_PASSWORD=drupal8
ENV MARIADB_DATABASE=drupal8

COPY dumps/ /docker-entrypoint-initdb.d/
COPY zzzz-truncate-caches.sql /docker-entrypoint-initdb.d/

RUN /opt/bitnami/scripts/mariadb/setup.sh

# This image is used to copy the installed databases and configure MySQL.
FROM bitnami/mariadb:10.4

COPY --from=build --chown=1001 /bitnami/mariadb /bitnami/mariadb
