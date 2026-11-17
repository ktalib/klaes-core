"""
Geodatabase Catalog for the Survey Plan card (Recording 5, 2026-11-16): a browser cannot read an Esri File
Geodatabase, so the server lists what is inside it and sends back only the layers picked, as GeoJSON.

  gdb_catalog.py list <folder.gdb>                         -> JSON on stdout: every layer with its feature dataset
  gdb_catalog.py read <folder.gdb> <out.json> <max> [--bbox=x1,y1,x2,y2] L1 L2 -> out.json: {layers: [{name, crs, count, truncated, fc}]}

Coordinates are left as stored (no reprojection): the card converts them with its own Minna definitions, as it does
for a shapefile's .prj (an ESRI CRS leaves out the Minna datum shift). Run with the gis-py venv (pyogrio / GDAL).
"""
import json
import os
import sys
import tempfile
import warnings

warnings.filterwarnings('ignore')

import pyogrio
import pyogrio.raw as raw


def datasets(gdb):
    """Layer name -> its feature dataset, from the GDB_Items system table (paths like \\Dataset\\Layer)."""
    out = {}
    try:
        meta, _, _, fields = raw.read(gdb, layer='GDB_Items', read_geometry=False, columns=['Path', 'Name'], LIST_ALL_TABLES='YES')
        cols = list(meta['fields'])
        paths = fields[cols.index('Path')]
        for p in paths:
            if not p:
                continue
            parts = [x for x in str(p).split('\\') if x]
            if len(parts) == 2:
                out[parts[1].lower()] = parts[0]
    except Exception:
        pass
    return out


def crs_name(wkt):
    if not wkt:
        return ''
    for key in ('PROJCRS["', 'PROJCS["', 'GEOGCRS["', 'GEOGCS["'):
        i = wkt.find(key)
        if i >= 0:
            return wkt[i + len(key):wkt.find('"', i + len(key))]
    return wkt[:60]


def cmd_list(gdb):
    ds = datasets(gdb)
    layers = []
    for name, geom in pyogrio.list_layers(gdb):
        name = str(name)
        if geom is None:   # tables: nothing to draw
            continue
        try:
            info = pyogrio.read_info(gdb, layer=name)
            count, crs = int(info.get('features') or 0), info.get('crs') or ''
        except Exception:
            count, crs = None, ''
        layers.append({'name': name, 'dataset': ds.get(name.lower(), ''), 'geometry': str(geom), 'count': count, 'crs': crs_name(crs), 'crs_wkt': crs})
    print(json.dumps({'layers': layers}))


def cmd_read(gdb, out, cap, names, bbox=None):
    result = []
    for name in names:
        info = pyogrio.read_info(gdb, layer=name)
        total = int(info.get('features') or 0)
        meta, _, geom, fields = raw.read(gdb, layer=name, max_features=cap, bbox=bbox)
        tmp = tempfile.NamedTemporaryFile(suffix='.geojson', delete=False)
        tmp.close()
        os.unlink(tmp.name)
        try:
            # GeoJSON as stored: no RFC 7946 reprojection to WGS 84.
            raw.write(tmp.name, geom, fields, fields=meta['fields'], driver='GeoJSON', geometry_type=meta['geometry_type'],
                      crs=None, encoding='UTF-8', layer=name, RFC7946='NO')
            with open(tmp.name, encoding='utf-8') as fh:
                fc = json.load(fh)
        finally:
            if os.path.exists(tmp.name):
                os.unlink(tmp.name)
        fc.pop('crs', None)
        result.append({'name': name, 'crs': crs_name(meta.get('crs') or ''), 'crs_wkt': meta.get('crs') or '', 'count': total,
                       'truncated': len(fc.get('features', [])) >= cap, 'bbox': bool(bbox), 'fc': fc})
    with open(out, 'w', encoding='utf-8') as fh:
        json.dump({'layers': result}, fh)


if __name__ == '__main__':
    if sys.argv[1] == 'list':
        cmd_list(sys.argv[2])
    elif sys.argv[1] == 'read':
        rest = sys.argv[5:]
        box = next((a for a in rest if a.startswith('--bbox=')), None)
        names = [a for a in rest if not a.startswith('--bbox=')]
        cmd_read(sys.argv[2], sys.argv[3], int(sys.argv[4]), names, tuple(float(v) for v in box[7:].split(',')) if box else None)
