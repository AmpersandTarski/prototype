#!/usr/bin/env bash
# Regenereer alle gecommitte staal-artefacten uit de ruwe FC5-bronbestanden.
# Vereist openpyxl op de host + de 6 CXX-bestanden in ~/git/FC5/Eisen en Dekkingen Johan.
# De merge-gate (test/run-regression.sh) draait dit NIET; het gebruikt de gecommitte artefacten.
set -euo pipefail
cd "$(dirname "$0")/.."
python3 tools/make_sample.py
python3 tools/normalize.py e2e/sample-raw.json e2e/data --split
cp e2e/data/compile.xlsx model/populatie.xlsx   # compile-partitie → INCLUDE-doel
echo "artefacten: model/populatie.xlsx, e2e/data/{runtime.xlsx,partition.json}, e2e/sample-raw.json"
