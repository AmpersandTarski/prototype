#!/usr/bin/env bash

# Make sure script stops on any error
set -e

project=${1:-project-administration}
model=${2:-ProjectAdministration.adl}

echo "🛠️ Using project: $project"
echo "📄 Using model: $model"


echo "🔄 Switching to frontend directory..."
cd frontend/src/app/ || exit 1

echo "🧹 Preparing generated directory..."
if [ -d "generated" ]; then
  echo "📦 Moving existing generated → generated.bak"
  rm -rf generated.bak
  mv generated generated.bak
else
  echo "ℹ️ No previous generated folder to back up"
  rm -rf generated.bak
fi

mkdir -p generated

if [ -d "generated.bak/.templates" ]; then
  echo "📁 Restoring .templates"
  cp -r generated.bak/.templates generated/
fi

if [ -f "generated.bak/project.module.ts" ]; then
  echo "📄 Restoring project.module.ts"
  cp generated.bak/project.module.ts generated/
fi

rm -rf generated.bak
cd ../../../ || exit 1

adl_path="/var/www/test/projects/${project}/model/${model}"

echo "⚙️ Compiling ADL into backend code ..."
if ! docker exec -it prototype sh -c "ampersand proto --no-frontend ${adl_path} --proto-dir /var/www/backend --crud-defaults cRud --verbose"; then
  echo "❌ Backend generation failed. Aborting script."
  exit 1
fi

echo "⚙️ Compiling ADL into frontend source code ..."
if ! docker exec -it prototype sh -c "ampersand proto --frontend-version Angular --no-backend ${adl_path} --proto-dir /var/www/frontend/src/app/generated --crud-defaults cRud --verbose"; then
  echo "❌ Frontend generation failed. Aborting script."
  exit 1
fi

echo "🧱 Building frontend from generated source code ..."
cd frontend || exit 1
npm install
npm run build
cd ../

echo "🚚 Copying generated files to html/"
rm -rf html/*
cp -r backend/public/ html/
cp -r frontend/dist/prototype-frontend/* html/

echo "✅ Done. Checkout your changes on: http://localhost"
