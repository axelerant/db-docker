# This image is used to copy and install the databases.
ARG BASE_IMAGE=drud/ddev-dbserver-mariadb-10.4:v1.17.0
FROM ${BASE_IMAGE} AS build
ARG BASE_IMAGE_USER
ARG BASE_IMAGE_PASSWORD
ARG BASE_IMAGE_DATABASE

ENV ALLOW_EMPTY_PASSWORD=yes
ENV MARIADB_USER=${BASE_IMAGE_USER:-db}
ENV MARIADB_PASSWORD=${BASE_IMAGE_PASSWORD:-db}
ENV MARIADB_DATABASE=${BASE_IMAGE_DATABASE:-db}

COPY create_init_db.sh /

COPY dumps/ /docker-entrypoint-initdb.d/
COPY zzzz-truncate-caches.sql /docker-entrypoint-initdb.d/

RUN /create_init_db.sh
RUN rm -rf /var/lib/mysql/*
RUN chmod -R ugo+rw /mysqlbase && find /mysqlbase -type d | xargs chmod ugo+rwx

# This image is used to copy the installed databases and configure MySQL.
FROM ${BASE_IMAGE}

COPY --from=build /mysqlbase /mysqlbase
