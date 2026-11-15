"""Cut a georeferenced image into web map tiles (XYZ, Web Mercator, 256 px WebP with transparency).

The deepest zoom is rendered from the source; every zoom above it is built from the four tiles below it,
so the large source file is read only once. Tiles that would be empty are not written.

Usage: make_tiles.py SOURCE OUT_DIR MIN_ZOOM MAX_ZOOM [--src-crs EPSG:32632] [--workers 24] [--quality 82]
Writes OUT_DIR/{z}/{x}/{y}.webp and OUT_DIR/tiles.json (bounds, zooms, count).
"""
import argparse, json, math, os, sys, time
from multiprocessing import Pool

import numpy as np
import rasterio
import rasterio.transform
from rasterio.crs import CRS
from rasterio.enums import Resampling
from rasterio.vrt import WarpedVRT
from rasterio.warp import transform_bounds
from rasterio.windows import from_bounds
from PIL import Image

T = 256
R = 6378137.0
HALF = math.pi * R


def tile_bounds(z, x, y):
    size = 2 * HALF / 2 ** z
    return (-HALF + x * size, HALF - (y + 1) * size, -HALF + (x + 1) * size, HALF - y * size)


def tile_range(z, b):  # b in EPSG:3857
    size = 2 * HALF / 2 ** z
    x0, x1 = int((b[0] + HALF) // size), int((b[2] + HALF) // size)
    y0, y1 = int((HALF - b[3]) // size), int((HALF - b[1]) // size)
    return range(max(0, x0), min(2 ** z - 1, x1) + 1), range(max(0, y0), min(2 ** z - 1, y1) + 1)


_ds = None
_src_crs = None


def _init(src, src_crs):
    global _ds, _src_crs
    _ds = rasterio.open(src)
    _src_crs = CRS.from_string(src_crs) if src_crs else None


def _save(rgba, path, quality):
    if rgba[..., 3].max() == 0:
        return False
    os.makedirs(os.path.dirname(path), exist_ok=True)
    Image.fromarray(rgba, 'RGBA').save(path, 'WEBP', quality=quality, method=4)
    return True


def render(args):
    z, x, y, out, quality = args
    path = os.path.join(out, str(z), str(x), f'{y}.webp')
    if os.path.exists(path):
        return 1
    b = tile_bounds(z, x, y)
    # A warped view exactly the tile's size and place: GDAL reads only the source blocks it needs.
    kw = dict(crs='EPSG:3857', transform=rasterio.transform.from_bounds(*b, T, T), width=T, height=T,
              resampling=Resampling.bilinear)
    if _src_crs is not None:
        kw['src_crs'] = _src_crs
    with WarpedVRT(_ds, **kw) as vrt:
        bands = min(vrt.count, 3)
        data = vrt.read(list(range(1, bands + 1)))
        mask = vrt.read_masks(1)
    if bands == 1:
        data = np.repeat(data, 3, axis=0)
    data = np.clip(data, 0, 255).astype(np.uint8)
    rgba = np.dstack([data[0], data[1], data[2], (mask > 0).astype(np.uint8) * 255])
    return 1 if _save(rgba, path, quality) else 0


def merge(args):
    z, x, y, out, quality = args
    path = os.path.join(out, str(z), str(x), f'{y}.webp')
    if os.path.exists(path):
        return 1
    canvas = Image.new('RGBA', (2 * T, 2 * T), (0, 0, 0, 0))
    found = False
    for dx in (0, 1):
        for dy in (0, 1):
            p = os.path.join(out, str(z + 1), str(2 * x + dx), f'{2 * y + dy}.webp')
            if os.path.exists(p):
                canvas.paste(Image.open(p).convert('RGBA'), (dx * T, dy * T))
                found = True
    if not found:
        return 0
    small = np.asarray(canvas.resize((T, T), Image.LANCZOS))
    return 1 if _save(small.copy(), path, quality) else 0


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('src'); ap.add_argument('out'); ap.add_argument('minz', type=int); ap.add_argument('maxz', type=int)
    ap.add_argument('--src-crs'); ap.add_argument('--workers', type=int, default=24); ap.add_argument('--quality', type=int, default=82)
    ap.add_argument('--bbox', help='limit to E,N,E,N in the source CRS (for a test)')
    a = ap.parse_args()

    with rasterio.open(a.src) as ds:
        crs = CRS.from_string(a.src_crs) if a.src_crs else ds.crs
        sb = ds.bounds
    if a.bbox:
        sb = [float(v) for v in a.bbox.split(',')]
    b3857 = transform_bounds(crs, 'EPSG:3857', *sb)
    ll = transform_bounds(crs, 'EPSG:4326', *sb)
    os.makedirs(a.out, exist_ok=True)
    total = 0
    t0 = time.time()
    xs, ys = tile_range(a.maxz, b3857)
    jobs = [(a.maxz, x, y, a.out, a.quality) for x in xs for y in ys]
    print(f'z{a.maxz}: {len(jobs)} tiles to render', flush=True)
    with Pool(a.workers, initializer=_init, initargs=(a.src, a.src_crs)) as pool:
        done = 0
        for i, r in enumerate(pool.imap_unordered(render, jobs, chunksize=16)):
            done += r
            if i and i % 5000 == 0:
                print(f'  {i}/{len(jobs)} ({time.time() - t0:.0f}s)', flush=True)
    total += done
    print(f'z{a.maxz}: {done} written, {time.time() - t0:.0f}s', flush=True)
    for z in range(a.maxz - 1, a.minz - 1, -1):
        xs, ys = tile_range(z, b3857)
        jobs = [(z, x, y, a.out, a.quality) for x in xs for y in ys]
        with Pool(a.workers) as pool:
            done = sum(pool.imap_unordered(merge, jobs, chunksize=16))
        total += done
        print(f'z{z}: {done} written, {time.time() - t0:.0f}s', flush=True)
    json.dump({'source': os.path.basename(a.src), 'bounds': ll, 'minzoom': a.minz, 'maxzoom': a.maxz, 'tiles': total,
               'format': 'webp', 'made': time.strftime('%Y-%m-%d %H:%M')}, open(os.path.join(a.out, 'tiles.json'), 'w'), indent=1)
    print('done', total, 'tiles', f'{time.time() - t0:.0f}s')


if __name__ == '__main__':
    main()
