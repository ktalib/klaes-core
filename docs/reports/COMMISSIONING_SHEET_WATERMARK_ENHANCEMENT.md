# File Commissioning Sheet - Watermark Centering & Opacity Enhancement

## Changes Implemented

### 🎯 **Watermark Positioning - Now Centered**
**Previous:** Watermark stretched across entire page from (0,0) position
**New:** Watermark properly centered using calculated positioning

```javascript
// NEW: Center calculation
const pageWidth = 210;        // A4 width in mm
const pageHeight = 297;       // A4 height in mm
const watermarkWidth = 150;   // Optimized width
const watermarkHeight = 200;  // Proportional height
const xPos = (pageWidth - watermarkWidth) / 2;   // 30mm from left edge
const yPos = (pageHeight - watermarkHeight) / 2;  // 48.5mm from top edge

doc.addImage(watermarkBase64, 'JPEG', xPos, yPos, watermarkWidth, watermarkHeight);
```

### 📊 **Opacity Enhancement - More Visible**
**Previous:** 0.25 (25% opacity) - too subtle, barely visible
**New:** 0.35 (35% opacity) - appropriately visible without interfering with text

```javascript
// OLD: Too subtle
doc.setGState(doc.GState({opacity: 0.25}));

// NEW: More visible but still professional
doc.setGState(doc.GState({opacity: 0.35}));
```

## Technical Improvements

### Position Calculation
| Measurement | Value | Purpose |
|-------------|--------|---------|
| Page Width | 210mm | A4 standard width |
| Page Height | 297mm | A4 standard height |
| Watermark Width | 150mm | Optimized for centering |
| Watermark Height | 200mm | Proportional to width |
| X Position | 30mm | Centered horizontally |
| Y Position | 48.5mm | Centered vertically |

### Visual Impact
- **Before:** Watermark covered 100% of page area
- **After:** Watermark covers ~50% of page area, perfectly centered
- **Opacity Increase:** 40% more visible (from 25% to 35%)

## Benefits

✅ **Perfect Centering:** Mathematical calculation ensures precise center placement
✅ **Better Visibility:** 35% opacity makes watermark appropriately visible
✅ **Professional Appearance:** Balanced look without overwhelming content
✅ **Improved Proportions:** 150x200mm size creates better visual harmony
✅ **Content Preservation:** Text remains highly readable with enhanced watermark

## File Modified
- `resources/views/generate_fileno/mls_js.blade.php` - Enhanced watermark positioning and opacity

## Result
The File Commissioning Sheet PDF now features a properly centered watermark with increased visibility (35% opacity) that maintains professional appearance while being clearly visible as a background security feature.

### Visual Comparison
```
BEFORE: [0,0]────────────────────[210,297]
        │ Watermark covers entire page │
        │ 25% opacity - barely visible │
        └─────────────────────────────┘

AFTER:  [0,0]────────────────────[210,297]
        │     [30,48.5]──────────      │
        │     │   CENTERED    │      │
        │     │  WATERMARK    │      │
        │     │ 35% opacity   │      │
        │     └───────────────┘      │
        └─────────────────────────────┘
```

The watermark now appears as a centered, appropriately visible background element that enhances document security without compromising readability.