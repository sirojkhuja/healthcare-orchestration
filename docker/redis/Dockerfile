FROM redis:8.6.0-alpine

RUN apk add --no-cache openssl \
    && mkdir -p /certs \
    && openssl req -x509 -newkey rsa:2048 -sha256 -days 3650 -nodes \
        -subj "/CN=redis" \
        -keyout /certs/redis.key \
        -out /certs/redis.crt \
    && cp /certs/redis.crt /certs/ca.crt

COPY docker/redis/redis.conf /usr/local/etc/redis/redis.conf

CMD ["redis-server", "/usr/local/etc/redis/redis.conf"]
