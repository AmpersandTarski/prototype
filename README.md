# Ampersand Prototype Framework
Prototype framework that transforms your Ampersand model into a web application

## User documenation
* For user documentation, see: https://ampersandtarski.github.io/

## Docs
* Documentation about the prototype framework (this repository) is maintained in the [developer documentation](./docs) folder

## OpenTelemetry dependencies

[OpenTelemetry documentation]()

### Build test image for RAP

```bash
docker build --tag ampersandtarski/prototype-framework:latest .
```

### Test

Run the php in server mode on the bare application

```bash
docker run --rm -it --name proto -p 8080:80 \
    -e OTEL_SDK_DISABLED=false \
    -e OTEL_TRACES_EXPORTER=console \
    -e OTEL_METRICS_EXPORTER=console \
    -e OTEL_LOGS_EXPORTER=console \
    proto-test:latest \
    php -S 0.0.0.0:80 -t /var/www/html/api/v1
```