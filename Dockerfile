FROM bitnami/php-fpm
RUN apt update
RUN apt install ffmpeg -y