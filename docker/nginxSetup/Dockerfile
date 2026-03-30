FROM php:7.4-fpm


RUN apt-get update && apt-get install -y nginx \
    && ln -sf /dev/stdout /var/log/nginx/access.log \
            && ln -sf /dev/stderr /var/log/nginx/error.log \
        && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/*


COPY . /var/www/html
COPY ./docker/nginxSetup/nginx/default.conf /etc/nginx/sites-enabled/default

COPY ./docker/nginxSetup/bootstrap.sh /bin/

CMD ["bootstrap.sh"]
