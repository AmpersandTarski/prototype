# Ampersand compiler
The prototype framework depends on a compatible [Ampersand compiler](https://github.com/AmpersandTarski/Ampersand) to takes your Ampersand script (ADL files) and transform it into model files for the backend and generate a UI for the frontend.

Backend model files are generated in the [generics folder](../generics/). These are picked up by the backend implementation. See [README](../generics/README.md) for more information about which files are generated.

Frontend UI is generated using the HTML and JS templates specified in [templates folder](../templates/). These output components consisting of HTML views and JS controllers are put into [public/app/project folder](../public/app/project/). These are picked up by the frontend application.

Together with the prototype framework, the backend model files and the generated frontend files provide for a complete prototype application.

## Compiler version constraints
As of Ampersand compiler version 5.x, the compiler checks if its version is compatible with the deployed prototype framework. The prototype framework specifies the compatible compiler version(s) by means of semantic versioning constraints specified in [compiler-version.txt](../generics/compiler-version.txt).

The compiler uses Haskell package [Salve](https://hackage.haskell.org/package/salve) to check the constraints. See documentation of Salve to understand the contraint language.

## On-board Ampersand compiler
The [Docker file](../Dockerfile) of the prototype framework includes a compatible Ampersand compiler in the container. Somewhere in the build script the following line is specified
> `COPY --from=ampersandtarski/ampersand:v4.6 /bin/ampersand /usr/local/bin`

This copies a pre-compiled and released Ampersand compiler from related image from Docker Hub.

You can make use of this compiler when building your own prototype application. Simply by extending the prototype-framework image and calling the compiler in a `RUN` statement like so:

```Dockerfile
FROM ampersandtarski/prototype-framework:v1.14

# The script content
COPY model /usr/local/project/

# Generate prototype application using Ampersand compiler
RUN ampersand proto /usr/local/project/script.adl \
  --proto-dir /var/www \
  --verbose

RUN chown -R www-data:www-data /var/www/log /var/www/data

WORKDIR /var/www
```

For a complete example and template folder for your project take a look at the [project-template repository](https://github.com/AmpersandTarski/project-template)

## Adding custom HTML templates
If you are using your own `VIEW` definitions and custom `BOX` specifications with custom HTML templates in your Ampersand script files, you need to copy them to the [templates folder](../templates/) BEFORE running the compiler.

You can add the following line to your Docker file:
```Dockerfile
# If you have custom templates, they need to be copied to where the Ampersand compiler expects them (/var/www)
RUN cp -r -v /usr/local/project/shared/templates /var/www/
```

## Build with custom Ampersand compiler
For developers that work on the Ampersand compiler itself it may be convenient to copy a locally build Ampersand compiler into the prototype-framework. You can do this by
a) injecting the custom Ampersand compiler in a specific prototype project directly or b) locally building a new prototype-framework image.

### Option A: inject custom compiler in prototype image
The quickest and most easiest way is to inject a custom Ampersand compiler directly in your prototype image. Update your Docker file and add the following line BEFORE running the compiler:

Custom compiler that is released on Github:
```Dockerfile
# Lines to add specific compiler version (from Github releases)
ADD https://github.com/AmpersandTarski/Ampersand/releases/download/Ampersand-v4.1.0/ampersand /usr/local/bin/ampersand
RUN chmod +x /usr/local/bin/ampersand
```

Custom compiler from specific (local) Docker image
```Dockerfile
# Line to add specific compiler version from some (local) Docker image
COPY --from=ampersandtarski/ampersand:local /bin/ampersand /usr/local/bin
```

Custom compiler from local binary
```Dockerfile
COPY /path/to/bin/ampersand /usr/local/bin
```

### Option B: locally build prototype-framework image
For option A: replace the following line in the [Docker file](../Dockerfile) of this repo:
> `COPY --from=ampersandtarski/ampersand:v4.6 /bin/ampersand /usr/local/bin`

Copy the compiler from a locally build Ampersand image ór from local bin directly instead of the `ampersandtarski/ampersand` image.


