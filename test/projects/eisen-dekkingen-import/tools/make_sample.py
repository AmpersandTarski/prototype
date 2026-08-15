#!/usr/bin/env python3
"""Selecteer een klein, heterogeen staal van ESE's uit de 6 CXX-bronbestanden en dump hun
volledige ruwe rijen naar e2e/sample-raw.json (de gedeelde grondwaarheid). Feature-gedreven:
neem een ESE op zodra die een nog niet gedekte variant toevoegt (norm, hoofdeis, multi-organisme,
multi-taal verklaring, dekkingcode-';', land-null, lange tekst, eind-datum, ALT, EKK-soorten).

Draaien op de host (openpyxl vereist):  python3 tools/make_sample.py
"""
import openpyxl, os, json, glob
from collections import defaultdict

DIR = "/Users/stef/git/FC5/Eisen en Dekkingen Johan"
OUT = os.path.join(os.path.dirname(__file__), "..", "e2e", "sample-raw.json")
# CAT/CFA/CBB dekken vrijwel alle varianten; CBN/CZZ leveren cross-file product/land-variatie.
FILES = ["CAT", "CFA", "CBB", "CBN", "CZZ"]
MAX_PER_FILE = 14

def norm(v):
    if v is None: return None
    if isinstance(v, str):
        s = v.strip()
        return s if s else None
    return v

def read_file(code):
    path = glob.glob(os.path.join(DIR, f"eisen en dekkingen {code}.xlsx"))[0]
    wb = openpyxl.load_workbook(path, read_only=True, data_only=True)
    ws = wb["Select select"]
    it = ws.iter_rows(values_only=True)
    header = [(h.strip() if isinstance(h, str) else h) for h in next(it)]
    rows_by_ese = defaultdict(list)
    idx = {h: i for i, h in enumerate(header)}
    for row in it:
        vals = [norm(v) for v in row]
        ese = vals[idx["ESE_ESE_ID"]]
        if ese is not None:
            # datetimes → ISO string (JSON-serialiseerbaar; datum-only)
            out = []
            for v in vals:
                if hasattr(v, "isoformat"):
                    out.append(v.date().isoformat() if hasattr(v, "date") else v.isoformat())
                else:
                    out.append(v)
            rows_by_ese[ese].append(out)
    wb.close()
    return header, idx, rows_by_ese

def features(rows, idx):
    def col(name): return idx[name]
    feats = set()
    orgs = set(); taals = set(); soorten = set()
    for r in rows:
        if r[col("ESE_NORM_WAARDE")]: feats.add("ese_norm")
        if r[col("HOOFDEIS")]: feats.add("hoofdeis")
        dc = r[col("DEKKING_CODE")]
        if dc and ";" in str(dc): feats.add("dekkingcode_multi")
        elif dc: feats.add("dekkingcode_single")
        if r[col("LAND_CODE")] is None: feats.add("land_null")
        if r[col("OGE_ID")] is not None: orgs.add(r[col("OGE_ID")])
        if r[col("ESE_TAAL")]: taals.add(r[col("ESE_TAAL")])
        if r[col("DKG_NORM_WAARDE")]: feats.add("dkg_norm")
        if r[col("DKG_VERKLARINGSCONCEPT")]: feats.add("dkg_verkl")
        if r[col("ESE_VERKLARINGSCONCEPT")]: feats.add("ese_verkl")
        if r[col("EKK_NAAM")]: soorten.add(r[col("EKK_NAAM")]); feats.add("ekk")
        if r[col("ALT")]: feats.add("alt")
        if r[col("EKK_EIND_DATUM")] or r[col("DKG_EIND_DATUM")] or r[col("ESE_EIND_DATUM")]:
            feats.add("eind_datum")
        for c in ("ESE_VERKLARINGSTEKST", "ESE_EXTERNE_MEMO", "ESE_INTERNE_MEMO", "DKG_VERKLARINGSTEKST"):
            v = r[col(c)]
            if v and len(str(v)) > 254: feats.add("long_text")
    if len(orgs) > 1: feats.add("organisme_multi")
    if len(orgs) == 1: feats.add("organisme_single")
    if len(taals) > 1: feats.add("ese_taal_multi")
    return feats, soorten

def main():
    covered = set(); soorten_seen = set()
    selected = []      # (file_code, ese_id)
    header = None
    all_rows = []      # flat rows with file tag
    for code in FILES:
        hdr, idx, rows_by_ese = read_file(code)
        header = hdr
        picked = 0
        # deterministische volgorde: op ese-id
        for ese in sorted(rows_by_ese, key=lambda x: str(x)):
            if picked >= MAX_PER_FILE: break
            rows = rows_by_ese[ese]
            feats, soorten = features(rows, idx)
            new = (feats - covered) or (soorten - soorten_seen)
            if new or picked < 3:   # altijd een paar 'gewone' per bestand
                covered |= feats; soorten_seen |= soorten
                selected.append((code, ese))
                for r in rows:
                    all_rows.append([code] + r)
                picked += 1
        print(f"{code}: picked {picked}", flush=True)
    # header: prefix FILE, hernoem dubbele DEKKING_CODE (col31) en de rownummer-kolom (col0)
    out_header = ["FILE"] + list(header)
    out_header[1] = "ROWNUM"           # was '   '
    out_header[32] = "DEKKING_CODE_DUP"  # col31 == col2 (bewezen gelijk); alleen ter ontdubbeling
    data = {"header": out_header, "rows": all_rows,
            "features_covered": sorted(covered), "n_ese": len(selected)}
    with open(OUT, "w") as f:
        json.dump(data, f, ensure_ascii=False)
    print(f"wrote {OUT}: {len(selected)} ESE, {len(all_rows)} rows")
    print("features:", sorted(covered))

if __name__ == "__main__":
    main()
