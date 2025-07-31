# clear generated frontend
cd frontend/src/app/
mv generated generated.bak
mkdir generated
cp -r generated.bak/.templates generated/
cp generated.bak/project.module.ts generated/
rm -r generated.bak
cd ../../../

# generate backend and frontend
docker exec -it prototype sh -c "ampersand proto --no-frontend /var/www/project/main.adl --proto-dir /var/www/backend --crud-defaults cRud --verbose"
docker exec -it prototype sh -c "ampersand proto --frontend-version Angular --no-backend /var/www/project/main.adl --proto-dir /var/www/frontend/src/app/generated --crud-defaults cRud --verbose"

# build fronted
cd frontend
npm i
npm run build
cd ../

# copy generated files to html folder
rm -r html/*
cp -r backend/public/ html/
cp -r frontend/dist/prototype-frontend/* html/
