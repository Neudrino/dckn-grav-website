# Dockerfile for Grav CMS on SnapDeploy
# linuxserver/grav bundles quark2 + 10 plugins; only brevo + form-captcha-hcaptcha
# are installed via GPM. For local dev use docker-compose.yml + setup-plugins.sh.

# Stage 1: Merge tracked content into image's bundled user/ dir and install missing plugins
FROM lscr.io/linuxserver/grav:latest AS builder
COPY user/ /app/www/public/user/
COPY install-plugins.sh /tmp/install-plugins.sh
RUN chmod +x /tmp/install-plugins.sh && \
    GRAV_ROOT=/app/www/public /tmp/install-plugins.sh

# Stage 2: Final image — runtime init-grav-config/run moves user/ to /config/www/user/
FROM lscr.io/linuxserver/grav:latest AS final
COPY --from=builder /app/www/public/user/ /config/www/user/
COPY entrypoint.sh /custom-cont-init.d/99-secrets.sh
RUN chmod +x /custom-cont-init.d/99-secrets.sh
EXPOSE 80
