#!/usr/bin/env python3
"""Verbreding: normaliseer de VOLLEDIGE bron (alle 6 CXX-bestanden) naar één interface-xlsx.
Streamt de ruwe rijen (materialiseert ze niet allemaal) en hergebruikt normalize.build/write.

  python3 tools/build_full.py <outdir>        # schrijft <outdir>/populatie-full.xlsx (gitignored)

Print de entiteits-tellingen zodat je ze onafhankelijk tegen analyze.json kunt kruisen.
"""
import os, sys, glob, openpyxl
import normalize  # zelfde map

DIR = "/Users/stef/git/FC5/Eisen en Dekkingen Johan"

# Kanonieke header identiek aan make_sample.py's out_header (FILE + 46 kolommen).
def canonical_header():
    path = sorted(glob.glob(os.path.join(DIR, "eisen en dekkingen C*.xlsx")))[0]
    wb = openpyxl.load_workbook(path, read_only=True, data_only=True)
    ws = wb["Select select"]
    header = [(h.strip() if isinstance(h, str) else h) for h in next(ws.iter_rows(values_only=True))]
    wb.close()
    out = ["FILE"] + list(header)
    out[1] = "ROWNUM"
    out[32] = "DEKKING_CODE_DUP"
    return out

def stream_rows(only=None):
    for path in sorted(glob.glob(os.path.join(DIR, "eisen en dekkingen C*.xlsx"))):
        code = os.path.basename(path).replace("eisen en dekkingen ", "").replace(".xlsx", "")
        if only and code != only:
            continue
        wb = openpyxl.load_workbook(path, read_only=True, data_only=True)
        ws = wb["Select select"]
        it = ws.iter_rows(values_only=True)
        next(it)  # skip header
        for row in it:
            out = [code]
            for v in row:
                if hasattr(v, "isoformat"):
                    out.append(v.date().isoformat() if hasattr(v, "date") else v.isoformat())
                elif isinstance(v, str):
                    s = v.strip(); out.append(s if s else None)
                else:
                    out.append(v)
            out[1] = None  # ROWNUM export-artefact, niet importeren
            yield out
        wb.close()
        print(f"streamed {code}", flush=True)

def main():
    outdir = sys.argv[1]
    only = sys.argv[2] if len(sys.argv) > 2 else None   # optioneel: één productgroep (bv. CFA)
    os.makedirs(outdir, exist_ok=True)
    header = canonical_header()
    model = normalize.build(header, stream_rows(only))
    name = f"populatie-{only}.xlsx" if only else "populatie-full.xlsx"
    stats = normalize.write_workbook(os.path.join(outdir, name), model, list(model["ese"].keys()))
    print(name, stats)
    print("verklaring atoms:", len(model["verkl"]))

if __name__ == "__main__":
    main()
