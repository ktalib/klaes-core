# VISUAL REFERENCE - Grid Card Layout

## Layout Breakdown

### Desktop View (4 columns)
```
┌─────────┬─────────┬─────────┬─────────┐
│ CARD 1  │ CARD 2  │ CARD 3  │ CARD 4  │
├─────────┼─────────┼─────────┼─────────┤
│ CARD 5  │ CARD 6  │ CARD 7  │ CARD 8  │
└─────────┴─────────┴─────────┴─────────┘
```

### Tablet View (3 columns)
```
┌─────────┬─────────┬─────────┐
│ CARD 1  │ CARD 2  │ CARD 3  │
├─────────┼─────────┼─────────┤
│ CARD 4  │ CARD 5  │ CARD 6  │
└─────────┴─────────┴─────────┘
```

### Mobile View (2 columns)
```
┌─────────┬─────────┐
│ CARD 1  │ CARD 2  │
├─────────┼─────────┤
│ CARD 3  │ CARD 4  │
└─────────┴─────────┘
```

---

## Individual Card Structure

```
┌──────────────────────────────┐
│                              │
│   [IMAGE PREVIEW]            │  ← A4 Aspect Ratio (210:297)
│   - Full size display        │  ← Image fits perfectly
│   - White background         │  ← Gradient fallback
│   - Centered content         │
│                              │
├──────────────────────────────┤
│ Filename.pdf                 │  ← File name (max 2 lines)
│                              │  ← With ellipsis overflow
│ [A4] [PDF] [Certificate]     │  ← Color-coded badges
│ 256 KB                       │  ← File size info
├──────────────────────────────┤
│  👁️ Preview   📝 Edit   🗑️ Delete  │  ← Action buttons
└──────────────────────────────┘
```

---

## Color Scheme for Paper Sizes

### Badge Colors
```
┌─────────────────────────────────────────┐
│ A4      → Sky Blue    (#0284c7)         │
│ A5      → Purple      (#7c3aed)         │
│ A3      → Amber       (#d97706)         │
│ Letter  → Blue        (#3b82f6)         │
│ Legal   → Pink        (#db2777)         │
│ Custom  → Green       (#16a34a)         │
└─────────────────────────────────────────┘
```

---

## Interaction States

### Normal State
```
┌──────────────────────────────┐
│        IMAGE AREA            │  ← Gray gradient
│                              │
├──────────────────────────────┤
│ Filename                     │  ← Normal text
│ [Badge] [Badge] [Badge]      │  ← Badges visible
│ Size: 256 KB                 │
├──────────────────────────────┤
│ 👁️  📝  🗑️                │  ← Buttons available
└──────────────────────────────┘
Border: 2px solid gray
```

### Hover State
```
╔══════════════════════════════╗
║        IMAGE AREA            ║  ← Still visible
║                              ║
╠══════════════════════════════╣
║ Filename                     ║  ← Lifted effect
║ [Badge] [Badge] [Badge]      ║
║ Size: 256 KB                 ║
╠══════════════════════════════╣
║ 👁️  📝  🗑️                ║  ← Highlighted on hover
╚══════════════════════════════╝
Border: 2px solid blue (primary)
Shadow: Blue glow effect
Transform: translateY(-4px) lift
```

### Button Hover
```
┌──────────────────────────────┐
│        IMAGE AREA            │
│                              │
├──────────────────────────────┤
│ Filename                     │
│ [Badge] [Badge] [Badge]      │
│ Size: 256 KB                 │
├──────────────────────────────┤
│ [👁️] 📝  🗑️                │  ← Preview button highlighted
│ ▲                            │  ← Turns blue on hover
│ Background changes to blue   │
│ Smooth transition effect     │
└──────────────────────────────┘
```

---

## Responsive Breakpoints

### Breakpoint Dimensions
```
Mobile:   < 768px   →  2 columns (140px min)
Tablet:   768-1024px →  3 columns (160px min)
Desktop:  > 1024px  →  4 columns (200px min)
```

### Grid Gap Spacing
```
Desktop:  1.5rem (24px) gap
Tablet:   1rem (16px) gap
Mobile:   0.75rem (12px) gap
```

### Padding Adjustments
```
Desktop:  1.5rem (24px) padding
Tablet:   1rem (16px) padding
Mobile:   0.75rem (12px) padding
```

---

## Text Hierarchy

### Card Content
```
File Name        → Bold, 0.85rem, primary gray
Paper Size       → Uppercase, small badge
Document Type    → Outline badge
File Size        → Smaller, muted gray
```

### Truncation Rules
- File name: Max 2 lines with ellipsis
- Badges: Wrap to multiple lines if needed
- File size: Single line
- All text breaks at word boundaries

---

## Animation Details

### Card Hover Animation
```
Duration:     300ms
Easing:       cubic-bezier(0.34, 1.56, 0.64, 1)
Transform:    translateY(-4px)
Border:       Smooth color transition
Shadow:       Glow effect at 0 8px 24px
Properties:   all 0.3s cubic-bezier(...)
```

### Button Interactions
```
Duration:     200ms
Easing:       ease
Color change: Instant on hover
Background:   Smooth transition
```

---

## Special Cases

### PDF Files
```
Card with PDF badge:
- [PDF] badge shows in red (#dc2626)
- Image area shows PDF icon (if not converted)
- Converted PDFs show [PDF Converted] in green

PDF File Preview:
- If converted to image: Shows thumbnail
- If not converted: Shows PDF icon
```

### Non-Image Files
```
Card with document files:
- Image area shows file type icon
- Icon is centered and muted gray color
- File name clearly visible
- All badges work same as images
```

### Large Files
```
File size display:
- 1 KB - Shows "1 KB"
- 1 MB - Shows "1.2 MB"
- 1 GB - Shows "1.5 GB"

Large text truncated:
- If text too long, truncated to 2 lines
- Ellipsis (...) added at end
- Hover shows full text in title attribute
```

---

## Action Button Behavior

### Below Cards Section
```
┌──────────────────────────────────────┐
│ [Card Grid Displayed Above]          │
└──────────────────────────────────────┘
           ↓ (space/gap)
┌──────────────────────────────────────┐
│         Action Buttons Below:         │
│                                      │
│  [Start Upload] [Cancel] [More]     │
│                                      │
└──────────────────────────────────────┘
```

### Button States
```
IDLE STATE (When files selected):
- Start Upload: PRIMARY (blue) - ENABLED
- Cancel: HIDDEN
- Upload More: HIDDEN
- View Uploaded: HIDDEN

UPLOADING STATE:
- Start Upload: HIDDEN
- Cancel: DESTRUCTIVE (red) - ENABLED
- Upload More: HIDDEN
- View Uploaded: HIDDEN

COMPLETE STATE:
- Start Upload: HIDDEN
- Cancel: HIDDEN
- Upload More: OUTLINE - ENABLED
- View Uploaded: PRIMARY (blue) - ENABLED
```

---

## Performance Features

### CSS Optimizations
- ✅ GPU-accelerated transforms
- ✅ CSS Grid native browser rendering
- ✅ Hardware-backed animations
- ✅ Efficient selector usage
- ✅ Minimal repaints/reflows

### JavaScript Optimizations
- ✅ Single render pass per update
- ✅ Event delegation for buttons
- ✅ Image lazy loading
- ✅ Error handling for failed previews
- ✅ Efficient DOM manipulation

---

## Mobile Considerations

### Touch Targets
- Card: Full area (140px+)
- Buttons: 40px+ height for easy touching
- Spacing: 12px+ between targets
- Feedback: Visible hover/active states

### Responsive Typography
- Desktop: 0.85rem font
- Tablet: 0.8rem font
- Mobile: 0.75rem font
- Line height: 1.4 for readability

### Gesture Support
- Tap to select file
- Long press shows context menu
- Swipe between cards (future)
- Pull to refresh (future)

---

## Accessibility Features

### Color Contrast
- All text meets WCAG AA standards
- Badges have sufficient contrast
- Buttons clear and visible
- Not relying on color alone

### Keyboard Navigation
- Tab through cards
- Enter to select/action
- Escape to close modals
- Arrow keys (future)

### Screen Readers
- Semantic HTML used
- ARIA labels where needed
- Button purposes clear
- File information announced

---

**Last Updated**: November 11, 2025  
**Version**: 1.0 - Final  
**Status**: ✅ Complete
