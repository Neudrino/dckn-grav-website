# =============================================================================
# Dockerfile for Grav CMS on SnapDeploy
#
# Multi-stage build:
#   Stage 1 (builder): Install GPM plugins + composer deps into user/
#   Stage 2 (final):   linuxserver/grav image with pre-built user/ directory
#
# This Dockerfile is used by SnapDeploy for cloud deployment.
# For local development, use docker-compose.yml + setup-plugins.sh instead.
# =============================================================================

# ---------------------------------------------------------------------------
# Stage 1: Builder — install plugins and composer dependencies
# ---------------------------------------------------------------------------
FROM lscr.io/linuxserver/grav:latest AS builder

# Copy our site content into the container's user directory
COPY user/ /config/www/user/
COPY install-plugins.sh /tmp/install-plugins.sh

# Set up symlinks (replicates what init-grav-config/run does at runtime)
RUN for d in user backup logs; do \
      if [ -d "/config/www/$d" ]; then \
        rm -rf "/app/www/public/$d"; \
        ln -s "/config/www/$d" "/app/www/public/$d"; \
      fi; \
    done && \
    if [ -f /config/www/robots.txt ]; then \
      rm -f /app/www/public/robots.txt; \
      ln -s /config/www/robots.txt /app/www/public/robots.txt; \
    fi && \
    chmod +x /tmp/install-plugins.sh && \
    GRAV_ROOT=/app/www/public /tmp/install-plugins.sh

# ---------------------------------------------------------------------------
# Stage 2: Final image
# ---------------------------------------------------------------------------
FROM lscr.io/linuxserver/grav:latest AS final

# Copy the pre-built user directory (with plugins installed) from builder
COPY --from=builder /config/www/user/ /config/www/user/

# Copy the entrypoint script for secrets injection
COPY entrypoint.sh /custom-cont-init.d/99-secrets.sh

# Ensure entrypoint is executable
RUN chmod +x /custom-cont-init.d/99-secrets.sh

# linuxserver/grav serves on port 80
EXPOSE 80
