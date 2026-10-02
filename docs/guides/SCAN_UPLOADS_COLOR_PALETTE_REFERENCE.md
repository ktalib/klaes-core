# Scan Uploads UI - Color Palette & Style Reference

## Complete Color System

### Primary Color Variants
**Blue (Primary)**
```
Base:        #3b82f6
Hover:       #2563eb
Light:       #dbeafe
Background:  bg-blue-100
Text:        text-blue-600
```
**Usage**: Main buttons, links, active states, primary actions

---

### Success Color Variants
**Green (Success)**
```
Base:        #10b981
Hover:       #059669
Light:       #d1fae5
Background:  bg-green-100
Text:        text-green-600
```
**Usage**: Completed items, success messages, positive feedback

---

### Warning Color Variants
**Amber (Warning)**
```
Base:        #f59e0b
Hover:       #d97706
Light:       #fef3c7
Background:  bg-amber-100
Text:        text-amber-600
```
**Usage**: Pending items, warnings, attention needed

---

### Danger Color Variants
**Red (Danger)**
```
Base:        #ef4444
Hover:       #dc2626
Light:       #fee2e2
Background:  bg-red-100
Text:        text-red-600
```
**Usage**: Destructive actions, errors, delete operations

---

### Info Color Variants
**Cyan (Info)**
```
Base:        #06b6d4
Hover:       #0891b2
Light:       #cffafe
Background:  bg-cyan-100
Text:        text-cyan-600
```
**Usage**: Informational content, notifications, tooltips

---

## Neutral Colors

**Borders**
```
Default:     #e5e7eb (var(--border))
Dark:        #d1d5db (var(--border-dark))
```

**Backgrounds**
```
Muted:       #f3f4f6 (var(--muted))
Muted Dark:  #e5e7eb (var(--muted-dark))
White:       #ffffff
```

**Text**
```
Primary:     #1f2937 (var(--text-primary))
Secondary:   #6b7280 (var(--text-secondary))
Muted:       #9ca3af (var(--text-muted))
```

---

## Component Color Reference

### Buttons

**Primary Button**
```css
.btn-primary {
  background-color: #3b82f6;
  color: white;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.btn-primary:hover {
  background-color: #2563eb;
  box-shadow: 0 6px 12px rgba(59, 130, 246, 0.3);
}

.btn-primary:focus {
  outline: 2px solid #dbeafe;
  outline-offset: 2px;
}
```

**Outline Button**
```css
.btn-outline {
  background-color: transparent;
  border: 1.5px solid #e5e7eb;
  color: #1f2937;
}

.btn-outline:hover {
  background-color: #f3f4f6;
  border-color: #d1d5db;
}
```

**Destructive Button**
```css
.btn-destructive {
  background-color: #ef4444;
  color: white;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.btn-destructive:hover {
  background-color: #dc2626;
  box-shadow: 0 6px 12px rgba(239, 68, 68, 0.3);
}
```

---

### Badges

**Badge Primary**
```css
.badge-primary {
  background-color: #dbeafe;
  color: #3b82f6;
  padding: 0.375rem 0.75rem;
}
```

**Badge Success**
```css
.badge-success {
  background-color: #d1fae5;
  color: #10b981;
}
```

**Badge Warning**
```css
.badge-warning {
  background-color: #fef3c7;
  color: #f59e0b;
}
```

**Badge Danger**
```css
.badge-danger {
  background-color: #fee2e2;
  color: #ef4444;
}
```

---

### Alert Boxes

**Alert Info**
```css
.alert-info {
  background-color: #cffafe;
  border: 1.5px solid #06b6d4;
  color: #06b6d4;
  padding: 1rem;
  border-left: 4px solid #06b6d4;
}
```

**Alert Success**
```css
.alert-success {
  background-color: #d1fae5;
  border: 1.5px solid #10b981;
  color: #10b981;
  border-left: 4px solid #10b981;
}
```

**Alert Warning**
```css
.alert-warning {
  background-color: #fef3c7;
  border: 1.5px solid #f59e0b;
  color: #f59e0b;
  border-left: 4px solid #f59e0b;
}
```

**Alert Danger**
```css
.alert-danger {
  background-color: #fee2e2;
  border: 1.5px solid #ef4444;
  color: #ef4444;
  border-left: 4px solid #ef4444;
}
```

---

### Icons

**Icon Color Usage**
```
Primary action:     text-blue-600      (#3b82f6)
Success/complete:   text-green-600     (#10b981)
Pending/warning:    text-amber-600     (#f59e0b)
Error/danger:       text-red-600       (#ef4444)
Information:        text-cyan-600      (#06b6d4)
Neutral:            text-gray-600      (#4b5563)
Muted:              text-gray-500      (#6b7280)
```

---

### Form Elements

**Input Focus State**
```css
.input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: inset 0 0 0 1px #3b82f6, 
              0 0 0 3px rgba(59, 130, 246, 0.1);
}
```

**Accent Color (Radio/Checkbox)**
```css
input[type="checkbox"],
input[type="radio"] {
  accent-color: #3b82f6;
}
```

---

### Cards

**Card Default**
```css
.card {
  background-color: white;
  border-radius: 0.75rem;
  border: 1px solid #e5e7eb;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
}
```

---

### Progress Bar

**Progress Gradient**
```css
.progress-bar {
  background: linear-gradient(90deg, #3b82f6, #06b6d4);
  height: 0.625rem;
  border-radius: 9999px;
}
```

---

### Shadows

**Subtle Shadow (Default)**
```css
box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
```

**Normal Shadow (Hover)**
```css
box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
```

**Elevated Shadow (Button Hover)**
```css
/* Blue button hover */
box-shadow: 0 6px 12px rgba(59, 130, 246, 0.3);

/* Green button hover */
box-shadow: 0 6px 12px rgba(16, 185, 129, 0.3);

/* Red button hover */
box-shadow: 0 6px 12px rgba(239, 68, 68, 0.3);
```

**Dialog Shadow**
```css
box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 
            0 10px 10px -5px rgba(0, 0, 0, 0.04);
```

---

## Tailwind CSS Class Mappings

### Colors in Use

**Text Colors**
```
text-gray-900      = #111827 (Darkest gray - main text)
text-gray-600      = #4b5563 (Medium gray - secondary)
text-gray-500      = #6b7280 (Light gray - descriptions)
text-blue-600      = #2563eb (Blue - primary accent)
text-green-600     = #059669 (Green - success)
text-amber-600     = #d97706 (Amber - warning)
text-red-600       = #dc2626 (Red - error)
text-cyan-600      = #0891b2 (Cyan - info)
```

**Background Colors**
```
bg-blue-100        = #dbeafe (Light blue)
bg-green-100       = #dcfce7 (Light green)
bg-amber-100       = #fef3c7 (Light amber)
bg-red-100         = #fee2e2 (Light red)
bg-cyan-100        = #cffafe (Light cyan)
bg-gray-50         = #f9fafb (Very light gray)
bg-gray-100        = #f3f4f6 (Light gray)
bg-white           = #ffffff (White)
```

**Border Colors**
```
border-gray-200    = #e5e7eb (Default border)
border-gray-300    = #d1d5db (Medium border)
border-gray-400    = #9ca3af (Dark border)
```

---

## Usage Examples

### Dashboard Stats Card
```html
<div class="card bg-white">
  <div class="p-6">
    <div class="flex items-start justify-between">
      <div>
        <p class="text-sm font-semibold text-gray-600">Today's Uploads</p>
        <p class="text-3xl font-bold text-gray-900 mt-2">24</p>
      </div>
      <div class="p-3 bg-blue-100 rounded-lg">
        <i class="h-6 w-6 text-blue-600">📤</i>
      </div>
    </div>
  </div>
</div>
```

### Success Alert
```html
<div class="alert alert-success">
  <div class="flex items-center gap-2">
    <i class="h-5 w-5">✓</i>
    <span>Upload completed successfully!</span>
  </div>
</div>
```

### Primary Action Button
```html
<button class="btn btn-primary gap-2">
  <i class="h-4 w-4">📤</i>
  Upload Files
</button>
```

### Badge
```html
<span class="badge badge-success">Completed</span>
```

---

## Accessibility Considerations

All color choices maintain:
- ✅ WCAG AA contrast ratios
- ✅ Color-blind friendly combinations
- ✅ Text + icon redundancy
- ✅ Focus states with outlines
- ✅ No color-only information

---

## Customization Guide

To modify colors globally, update the CSS variables:

```css
:root {
  --primary: #3b82f6;           /* Change all primary elements */
  --success: #10b981;           /* Change all success elements */
  --warning: #f59e0b;           /* Change all warning elements */
  --danger: #ef4444;            /* Change all error elements */
  --info: #06b6d4;              /* Change all info elements */
}
```

All components will automatically update!

---

## Color Theory Applied

- **Blue (#3b82f6)**: Professional, trustworthy - Primary actions
- **Green (#10b981)**: Positive, complete - Success states
- **Amber (#f59e0b)**: Attention, caution - Warnings
- **Red (#ef4444)**: Alert, danger - Critical actions
- **Cyan (#06b6d4)**: Cool, informative - Additional info

---

**Last Updated**: November 2024
**Version**: 1.0
**Status**: Production Ready

