# VISUAL GUIDE - Select Indexed File UI Improvements

## Dialog Layout

### Before (Unclear Selection)
```
┌─────────────────────────────────────┐
│ 🔍 Select Indexed File              │
├─────────────────────────────────────┤
│ [Search box]                        │
│                                     │
│ ┌─────────────────────────────────┐ │
│ │ 📁 FILE-001                     │ │  ← Selected file (hard to see)
│ │    Some File Name               │ │
│ │ [Badge] [Badge]                 │ │
│ └─────────────────────────────────┘ │
│ ┌─────────────────────────────────┐ │
│ │ 📁 FILE-002                     │ │
│ │    Some File Name               │ │
│ │ [Badge] [Badge]                 │ │
│ └─────────────────────────────────┘ │
├─────────────────────────────────────┤
│           [Cancel] [Select File]    │
└─────────────────────────────────────┘
```

### After (Crystal Clear Selection)
```
┌─────────────────────────────────────────────────────────┐
│ 🔍 Select Indexed File          ✓ Selected           │
│    Click on a file to select it     FILE-001          │
├─────────────────────────────────────────────────────────┤
│ 🔍 [Search box]                                         │
│                                                         │
│ █ ┌─────────────────────────────────────────────┐     │
│   │ 📁 FILE-001 (BLUE)              [✓ circle]  │  ← SELECTED
│   │    Some File Name                           │
│   │ [Badge] [Badge]                             │
│   └─────────────────────────────────────────────┘
│                                                         │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ 📁 FILE-002 (gray)                              │ │
│ │    Some File Name                               │ │
│ │ [Badge] [Badge]                                 │ │
│ └─────────────────────────────────────────────────────┘ │
├─────────────────────────────────────────────────────────┤
│                [Cancel] [✓ Confirm Selection]          │
│                          ↑ Enabled with pulse
└─────────────────────────────────────────────────────────┘
```

## Selection States

### Unselected File Item
```
┌─────────────────────────────────────┐
│ 📁 FILE-001                         │
│    Some File Name                   │
│ [Badge] [Badge]                     │
└─────────────────────────────────────┘

Indicators:
- Left border: Transparent/invisible
- Background: White
- Folder icon: Gray
- File number: Regular gray text
- No checkmark
- Hover: Light blue background
```

### Selected File Item
```
█ ┌───────────────────────────────────┐
  │ 📁 FILE-001 (BLUE)          [✓]   │
  │    Some File Name                 │
  │ [Badge] [Badge]                   │
  └───────────────────────────────────┘

Indicators:
- Left border: 3px SOLID BLUE (█)
- Background: Light blue tint
- Folder icon: Blue color (matches primary)
- File number: BOLD BLUE text
- Checkmark: Green circle with white checkmark (right)
- No hover needed - already highlighted
- Inset shadow for depth
```

## Header Sections

### Header Badge (Hidden When No Selection)
```
Default (not visible):
┌─────────────────────────────────────────┐
│ 🔍 Select Indexed File                  │
│    Click on a file to select it         │
└─────────────────────────────────────────┘

With Selection (appears with slide animation):
┌─────────────────────────────────────────────────────────┐
│ 🔍 Select Indexed File         ┌────────────────────┐  │
│    Click on a file...          │ ✓ Selected         │  │
│                                │    FILE-001        │  │
│                                └────────────────────┘  │
└─────────────────────────────────────────────────────────┘
                                  ↑ Green background
                                  ↑ Slide-in animation
```

## Button States

### Confirm Button - Disabled (No Selection)
```
┌──────────────────────────┐
│ ✓ Confirm Selection      │  ← Grayed out (50% opacity)
└──────────────────────────┘
State: Not clickable
Cursor: Not-allowed
Animation: None
```

### Confirm Button - Enabled (File Selected)
```
┌──────────────────────────┐
│ ✓ Confirm Selection      │  ← Bright blue
└──────────────────────────┘
    ↕️ Subtle pulse animation
    
State: Clickable
Cursor: Pointer
Animation: Continuous pulse (glow effect)
```

## Color Reference

### Semantic Colors
```
Primary Blue:       #3b82f6 ← Selection color, left border
Success Green:      #10b981 ← Confirmation color  
Light Blue BG:      rgba(59, 130, 246, 0.12) ← Selected background
Hover Blue BG:      rgba(59, 130, 246, 0.08) ← Hover background
Text Primary:       #1f2937 ← Regular text
Text Muted:         #6b7280 ← Disabled/secondary text
```

## Animation Timeline

### File Selection (200ms)
```
Start:                          End:
┌─────────────────────┐        █ ┌───────────────────┐
│ 📁 FILE-001         │        │ 📁 FILE-001  [✓]   │
│    Name             │  -->   │    Name            │
│ [Badge] [Badge]     │   →    │ [Badge] [Badge]    │
└─────────────────────┘        └───────────────────┘

Changes:
- Border: transparent → 3px blue
- Background: white → light blue
- Icon: gray → blue
- Text: gray → blue
- Checkmark: hidden → visible
- Duration: 200ms ease
```

### Header Badge Appearance (300ms)
```
Hidden:                         Visible:
              ┌─────────────┐
              │ ✓ Selected  │  ← Slides in from right
              │    FILE-001 │  ← Fades in
              └─────────────┘
Duration: 300ms ease-out
Transform: translateX(20px) → translateX(0)
Opacity: 0 → 1
```

### Confirm Button Pulse (Continuous)
```
At rest:                        After pulse:
┌──────────────────────┐        ┌──────────────────────┐
│ ✓ Confirm Selection  │  ◄──►  │ ✓ Confirm Selection  │
└──────────────────────┘        └──────────────────────┘
  Subtle glow                      Stronger glow
  Shadow: 0 4px 12px              Shadow: 0 8px 20px
  Duration: 2s infinite            Duration: 2s infinite
  Cycle: 0% → 50% → 100%
```

## Interaction Flow

### User Journey

```
1. Dialog Opens
   ↓
   File list loads, no selection badge visible
   Confirm button disabled
   ↓

2. User Clicks File
   ↓
   Selected file highlights with blue left border
   Checkmark appears on right
   File name turns blue
   ↓

3. Badge Appears (Header)
   ↓
   Green badge slides in from right
   Shows selected file number
   ↓

4. Confirm Button Activates
   ↓
   Button becomes clickable (blue)
   Pulse animation starts
   Button text: "✓ Confirm Selection"
   ↓

5. User Clicks Confirm
   ↓
   File is confirmed
   Dialog closes
   Selection updates main UI
```

## Mobile Responsiveness

### Desktop View (Full Badge)
```
┌───────────────────────────────────────────────────────┐
│ 🔍 Select File        ┌─────────────────┐            │
│                       │ ✓ Selected      │            │
│                       │    FILE-NUMBER  │            │
│                       └─────────────────┘            │
└───────────────────────────────────────────────────────┘
```

### Mobile View (No Header Badge)
```
┌─────────────────────┐
│ 🔍 Select File      │
│    Click to select  │
└─────────────────────┘
│
│ █ ┌───────────────┐
│   │ 📁 FILE-001   │
│   │ [✓] Selected  │
│   └───────────────┘
│

Note: Badge hidden on mobile for space
Selection still visible via blue border and checkmark
```

## Accessibility Features

### Multiple Indicators (Not Color-Only)
```
Selection shown by:
1. Blue left border (█)         ← Structural indicator
2. Blue folder icon             ← Icon color change
3. Bold blue text               ← Text styling
4. Green checkmark icon (✓)     ← Icon confirmation
5. Light blue background        ← Background color
6. Selected file badge in header ← Text label
```

### Keyboard Navigation (Ready for Enhancement)
```
Tab: Move between files
Space/Enter: Select file
Escape: Close dialog
(Currently works with mouse, keyboard enhancements coming)
```

---

**Visual Reference**: November 11, 2025  
**Status**: ✅ Complete
