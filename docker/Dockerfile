# Build the code.
FROM unocha/unified-builder:7.3-develop as builder

ARG  BRANCH_ENVIRONMENT

ENV  NODE_ENV=$BRANCH_ENVIRONMENT

# Copy only the files necessary to build the code (see .dockerignore in root).
COPY . /srv/www

WORKDIR /srv/www

# Clean up previous composer installation and run new one.
RUN rm -rf ./vendor && \
    # Update composer to avoid issues with missing drupal files.
    # @see https://github.com/drupal-composer/drupal-project/issues/282
    composer self-update && \
    COMPOSER_MEMORY_LIMIT=-1 composer install --no-interaction --prefer-dist

# Clean up previous npm installation and run new one.
RUN COMPOSER_MEMORY_LIMIT=-1 composer gulp

# Copy settings to default site location after creating it.
RUN mkdir -m 0775 -p html/sites/default && \
    cp -a docker/settings.php docker/services.yml docker/redis.services.yml html/sites/default

################################################################################

# Generate the image.
FROM unocha/php7-k8s:7.3-NR-develop

ARG VCS_REF
ARG VCS_URL
ARG BUILD_DATE
ARG GITHUB_ACTOR
ARG GITHUB_REPOSITORY
ARG GITHUB_SHA
ARG GITHUB_REF

ENV  NGINX_SERVERNAME=docstore.unocha.org \
     PHP_ENVIRONMENT=production \
     PHP_MEMORY_LIMIT=256M \
     PHP_MAX_CHILDREN=16 \
     GIT_BLAME=$GITHUB_ACTOR \
     GIT_REPO=$GITHUB_REPOSITORY \
     GIT_SHA=$GITHUB_SHA \
     GIT_REF=$GITHUB_REF

LABEL info.humanitarianresponse.build.date=$BUILD_DATE \
      info.humanitarianresponse.build.vcs-url=$VCS_URL \
      info.humanitarianresponse.build.vcs-ref=$VCS_REF

RUN mkdir -p /etc/nginx/custom

COPY --from=builder /srv/www/config /srv/www/config/
COPY --from=builder /srv/www/html /srv/www/html/
COPY --from=builder /srv/www/vendor /srv/www/vendor/
COPY --from=builder /srv/www/composer.json /srv/www/composer.json
COPY --from=builder /srv/www/composer.patches.json /srv/www/composer.patches.json
COPY --from=builder /srv/www/composer.lock /srv/www/composer.lock
COPY --from=builder /srv/www/docker/etc/nginx/apps/drupal/drupal.conf /etc/nginx/apps/drupal/drupal.conf
COPY --from=builder /srv/www/docker/etc/nginx/apps/drupal/fastcgi_drupal.conf /etc/nginx/apps/drupal/fastcgi_drupal.conf
COPY --from=builder /srv/www/docker/etc/nginx/map_block_http_methods.conf /etc/nginx/map_block_http_methods.conf
COPY --from=builder /srv/www/docker/etc/nginx/custom/01_docstore_files.conf /etc/nginx/custom/01_docstore_files.conf
COPY --from=builder /srv/www/docker/etc/nginx/sites-enabled/01_map_docstore_headers.conf /etc/nginx/sites-enabled/01_map_docstore_headers.conf
