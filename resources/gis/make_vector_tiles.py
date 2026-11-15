"""Kano GIS layers (F:\\GIS Data) -> PMTiles vector tiles for the Survey Plan card's Layers panel.

Usage: python make_vector_tiles.py OUT_DIR [layer ...]
Writes OUT_DIR/<layer>.pmtiles. With no layer names, all of them.

Minna data (the geodatabase, EPSG:26332) is declared with the card's own datum shift (Clarke 1880 RGS,
-92 -93 +122) so the tiles sit where the card plots the Beacon Register, not where a different Minna
transformation would put them. The shapefiles are UTM 32N on WGS 84 already.

Two parcel sets (user, 2026-11-15): kano_parcels (all attributes, KLAES staff) and
kano_parcels_public (shape, plot no., block, TP plan, layout, district, LGA: MLPP-SG surveyors).
"""
import os
import sys
import tempfile
import shutil

import pyogrio
from pyogrio.raw import read, write

G = r'F:\GIS Data'
GDB = os.path.join(G, 'ORIGINAL GEOMETRY.gdb')
MINNA_UTM32 = '+proj=utm +zone=32 +a=6378249.145 +rf=293.465 +towgs84=-92,-93,122,0,0,0,0 +units=m +no_defs'
WGS_UTM32 = 'EPSG:32632'

PUBLIC_PARCEL_FIELDS = ['plotNo', 'blockNo', 'tpPlanNo', 'approvedPlanNo', 'layoutName', 'districtName', 'lgaName']

LAYERS = {
    # name: (source, layer, crs, fields or None = all, minzoom, maxzoom)
    'kano_parcels': (GDB, 'Plot_Parcel', MINNA_UTM32, None, 12, 16),
    'kano_parcels_public': (GDB, 'Plot_Parcel', MINNA_UTM32, PUBLIC_PARCEL_FIELDS, 12, 16),
    'kano_beacons': (GDB, 'Beacon_Name', MINNA_UTM32, ['beaconName'], 13, 16),
    'kano_roads': (os.path.join(G, 'Kano Roads', 'kano_roads.shp'), None, WGS_UTM32, ['NAME', 'CATEGORY'], 10, 16),
    'kano_lga': (os.path.join(G, 'KANO_METRO', 'shp', 'LGA.shp'), None, WGS_UTM32, ['name', 'label'], 6, 14),
    'kano_control_beacons': (os.path.join(G, 'KANO_METRO', 'BEACONS.shp'), None, WGS_UTM32,
                             ['PILLAR_NO', 'LGA', 'LOCATION', 'N', 'E', 'H'], 8, 16),
}


def build(name, out_dir):
    src, layer, crs, fields, minz, maxz = LAYERS[name]
    meta, _fids, geom, values = read(src, layer=layer, columns=fields)
    # Dates and odd types travel as text: the tiles only display them.
    vals, names = [], []
    for f, v in zip(meta['fields'], values):
        names.append(f)
        vals.append(v.astype(str) if v.dtype.kind in 'Mm' else v)
    work = tempfile.mkdtemp(prefix='kgis_')
    try:
        tmp = os.path.join(work, name + '.pmtiles')
        write(tmp, geom, vals, fields=names, geometry_type=meta['geometry_type'], crs=crs,
              driver='PMTiles', layer=name,
              dataset_options={'MINZOOM': str(minz), 'MAXZOOM': str(maxz), 'NAME': name},
              layer_options={'MINZOOM': str(minz), 'MAXZOOM': str(maxz)})
        dest = os.path.join(out_dir, name + '.pmtiles')
        shutil.move(tmp, dest)
        print('%s: %d features, z%d-%d, %.1f MB' % (name, len(geom), minz, maxz, os.path.getsize(dest) / 1048576))
    finally:
        shutil.rmtree(work, ignore_errors=True)


def main():
    out = sys.argv[1]
    os.makedirs(out, exist_ok=True)
    for name in (sys.argv[2:] or list(LAYERS)):
        build(name, out)


if __name__ == '__main__':
    main()
