FROM ampersandtarski/prototype-framework:main

COPY . /usr/local/project/

# Run ampersand compiler to generate backend json model files
RUN ampersand proto --no-frontend /usr/local/project/model/main.adl \
  --proto-dir /var/www/backend \
  --crud-defaults cRud \
  --verbose

# Run ampersand compiler to generate new frontend
RUN ampersand proto --frontend-version Angular --no-backend /usr/local/project/model/main.adl \
  --proto-dir /var/www/frontend/src/app/generated \
  --crud-defaults cRud \
  --verbose

WORKDIR /var/www/frontend

# Build + bundle Angular frontend
RUN npx ng build

# Copy Angular frontend to public folder in web server
RUN cp -r /var/www/frontend/dist/prototype-frontend/* /var/www/html
