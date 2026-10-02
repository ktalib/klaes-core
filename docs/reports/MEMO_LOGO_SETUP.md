# Memo Template Logo Setup Instructions

## Logo Files Required

Place the following logo files in the `public/images/` directory:

### Ministry Logos (Header)
- **Left Ministry Logo**: `public/images/ministry-logo-left.png`
- **Right Ministry Logo**: `public/images/ministry-logo-right.png`

### Branding Logos (Footer)  
- **Left Branding Logo**: `public/images/branding-logo-left.png`
- **Right Branding Logo**: `public/images/branding-logo-right.png`

## Logo Specifications

### Header Logos (Ministry)
- **Size**: 80x80 pixels (will be displayed as 20x20 in Tailwind units)
- **Format**: PNG with transparent background recommended
- **Position**: Top left and top right of memo header
- **Style**: Official ministry/government logos

### Footer Logos (Branding)
- **Size**: 64x64 pixels (will be displayed as 16x16 in Tailwind units)
- **Format**: PNG with transparent background recommended
- **Position**: Bottom left and bottom right of memo footer
- **Style**: Branding/organizational logos with 60% opacity

## Directory Structure
```
public/
├── images/
│   ├── ministry-logo-left.png
│   ├── ministry-logo-right.png
│   ├── branding-logo-left.png
│   └── branding-logo-right.png
```

## Fallback Behavior
If logo files are not found, the image tags will show as broken images. Consider adding fallback text or hiding the containers if logos are missing.

## Print Optimization
The template includes print-specific CSS to ensure logos render correctly when printing:
- Uses `print-color-adjust: exact` for proper logo rendering
- Maintains logo positioning and sizing in print mode