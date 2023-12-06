FROM tolgee/tolgee

ENV HEALTHCHECK_PORT=8080

HEALTHCHECK --interval=10s --timeout=3s --retries=20 \
    CMD [[ "$(wget -q -O -  http://127.0.0.1:$HEALTHCHECK_PORT/actuator/health)" == *"UP"* ]] || exit 1
