"""Survey Plan Check: GeoJSON layers -> Esri File Geodatabase (.gdb zipped).
Usage: python geojson_to_gdb.py <input.json> <output.zip>
input.json = {"epsg": 26332, "name": "survey_plan", "layers": {"beacons": FeatureCollection, ...}}
Coordinates in each FeatureCollection are already X / Y in that EPSG system (the card converts them)."""
import json, os, sys, shutil, tempfile, zipfile
import pyogrio
from pyogrio.raw import read, write

def main(src, out):
    spec = json.load(open(src, encoding='utf-8'))
    epsg = int(spec['epsg'])
    name = ''.join(c if c.isalnum() or c == '_' else '_' for c in spec.get('name') or 'survey_plan')[:60] or 'survey_plan'
    work = tempfile.mkdtemp(prefix='spcgdb_')
    try:
        gdb = os.path.join(work, name + '.gdb')
        n = 0
        for layer, fc in spec['layers'].items():
            if not fc.get('features'):
                continue
            tmp = os.path.join(work, layer + '.geojson')
            json.dump(fc, open(tmp, 'w', encoding='utf-8'))
            meta, _fids, geom, fields = read(tmp)
            write(gdb, geom, fields, fields=meta['fields'], geometry_type=meta['geometry_type'],
                  crs='EPSG:%d' % epsg, driver='OpenFileGDB', layer=layer, append=n > 0,
                  dataset_options=None)
            n += 1
        if not n:
            raise SystemExit('nothing to write')
        with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
            for root, _d, files in os.walk(gdb):
                for f in files:
                    p = os.path.join(root, f)
                    z.write(p, os.path.relpath(p, work))
        print('ok', n)
    finally:
        shutil.rmtree(work, ignore_errors=True)

if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2])
