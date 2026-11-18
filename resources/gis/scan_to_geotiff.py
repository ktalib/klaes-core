"""Turn a scan georeferenced in ArcMap (world file .tfwx / .tfw beside it) into a north-up GeoTIFF that places itself.

The Survey Plan card reads a GeoTIFF's position from its own tags (pixel scale + tie point) and cannot use a side
world file or a rotated one, so the scan is warped north-up into the given system and written with that system's
EPSG code. JPEG-compressed, RGB, for loading in the browser.

Usage: scan_to_geotiff.py SOURCE.tif OUT.tif [--epsg 26332] [--res 0.25] [--quality 85]
"""
import argparse
from pathlib import Path

import rasterio
from affine import Affine
from rasterio.crs import CRS
from rasterio.enums import Resampling
from rasterio.warp import calculate_default_transform, reproject


def world_file(src: Path) -> Affine:
    for ext in ('.tfwx', '.tfw', '.tifw', '.wld'):
        wf = src.with_suffix(ext)
        if wf.exists():
            a, d, b, e, c, f = [float(v) for v in wf.read_text().split()[:6]]
            # A world file gives the centre of the top-left pixel; the GeoTIFF transform its corner.
            return Affine(a, b, c, d, e, f) * Affine.translation(-0.5, -0.5)
    raise SystemExit('no world file (.tfwx/.tfw) beside ' + str(src))


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('src'); ap.add_argument('out')
    ap.add_argument('--epsg', type=int, default=26332); ap.add_argument('--res', type=float, default=0.25)
    ap.add_argument('--quality', type=int, default=85)
    a = ap.parse_args()
    src, crs = Path(a.src), CRS.from_epsg(a.epsg)
    T = world_file(src)
    with rasterio.open(src) as ds:
        w, h = ds.width, ds.height
        dst_T, dw, dh = calculate_default_transform(crs, crs, w, h, *rasterio.transform.array_bounds(h, w, T), resolution=a.res)
        prof = dict(driver='GTiff', width=dw, height=dh, count=3, dtype='uint8', crs=crs, transform=dst_T, compress='JPEG',
                    jpeg_quality=a.quality, photometric='RGB', tiled=True, blockxsize=512, blockysize=512)
        with rasterio.open(a.out, 'w', **prof) as out:
            for b in (1, 2, 3):
                reproject(rasterio.band(ds, b), rasterio.band(out, b), src_transform=T, src_crs=crs, dst_transform=dst_T,
                          dst_crs=crs, resampling=Resampling.average, dst_nodata=255, init_dest_nodata=True)
    print(f'{a.out}: {dw} x {dh} px, {a.res} m, EPSG:{a.epsg}, bounds {rasterio.transform.array_bounds(dh, dw, dst_T)}')


if __name__ == '__main__':
    main()
