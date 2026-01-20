# Progress Tracking - Visual Guide

## Progress Modal During Migration

```
┌─────────────────────────────────────────────────────────┐
│  Migration in Progress                                  │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Type: Products                              [✓]        │
│  Current: Migrating: product-sku-123                    │
│  Estimated Time Remaining: 3 minutes                    │
│                                                         │
│  █████████████████████░░░░░░░░░░░░░░                    │
│  47%                                                    │
│                                                         │
│  ┌──────────────────────────────────────────────────┐  │
│  │ 47% Complete      94 of 200                       │  │
│  │ Success Rate:     98%                             │  │
│  └──────────────────────────────────────────────────┘  │
│                                                         │
│  ┌──────┐ ┌──────────┐ ┌──────────┐ ┌──────┐         │
│  │ Total│ │Processed │ │Successful│ │Failed│         │
│  │ 200  │ │    94    │ │    92    │ │  2   │         │
│  └──────┘ └──────────┘ └──────────┘ └──────┘         │
│                                                         │
│  [Close]  [Cancel Migration]                            │
└─────────────────────────────────────────────────────────┘
```

## Progress Modal - Completed

```
┌─────────────────────────────────────────────────────────┐
│  Migration in Progress                                  │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Type: Products                              [✓]        │
│  Current: Completed                                    │
│                                                         │
│  ████████████████████████████████████████████          │
│  Completed - 100%                                      │
│                                                         │
│  ┌──────────────────────────────────────────────────┐  │
│  │        ✓ Migration Complete!                     │  │
│  │  Total: 200 | Successful: 195 | Failed: 5        │  │
│  └──────────────────────────────────────────────────┘  │
│                                                         │
│  ┌──────┐ ┌──────────┐ ┌──────────┐ ┌──────┐         │
│  │ Total│ │Processed │ │Successful│ │Failed│         │
│  │ 200  │ │   200    │ │   195    │ │  5   │         │
│  └──────┘ └──────────┘ └──────────┘ └──────┘         │
│                                                         │
│  [Close]                                                │
└─────────────────────────────────────────────────────────┘
```

## Progress Modal - With Errors

```
┌─────────────────────────────────────────────────────────┐
│  Migration in Progress                                  │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Type: Products                              [X]        │
│  Current: Completed with errors                        │
│                                                         │
│  ████████████████████████████████████████████          │
│  Failed                                                 │
│                                                         │
│  ┌──────────────────────────────────────────────────┐  │
│  │        ✗ Migration failed                        │  │
│  │  See errors below for details                     │  │
│  └──────────────────────────────────────────────────┘  │
│                                                         │
│  ┌──────┐ ┌──────────┐ ┌──────────┐ ┌──────┐         │
│  │ Total│ │Processed │ │Successful│ │Failed│         │
│  │ 200  │ │   150    │ │   145    │ │  5   │         │
│  └──────┘ └──────────┘ └──────────┘ └──────┘         │
│                                                         │
│  Errors:                                                │
│  • ... and 15 more errors                               │
│  • SKU-123: Failed to download image                   │
│  • SKU-456: Invalid price value                        │
│  • SKU-789: Category not found                         │
│                                                         │
│  [Close]                                                │
└─────────────────────────────────────────────────────────┘
```

## Dashboard Status Display

```
┌─────────────────────────────────────────────────────────┐
│  Magento Migrator Dashboard                             │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ✓ Connection successful                                │
│                                                         │
│  Migrating Products (47%)...                            │
│                                                         │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐  │
│  │ Products │ │Categories│ │Customers │ │  Orders  │  │
│  │ 94 / 200 │ │  45 / 50 │ │  0 / 0   │ │  0 / 0   │  │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘  │
│                                                         │
│  [Migrate Products] [Migrate Categories] ...            │
└─────────────────────────────────────────────────────────┘
```

## Key Elements

### Color Coding:
- **Blue** (#2271b1) - Progress bar, active elements
- **Green** (#00a32a) - Success, completed status
- **Red** (#d63638) - Errors, failed status
- **Yellow** (#dba617) - Warnings, cancelled status
- **Gray** (#666) - Secondary text, labels

### Animations:
- Progress bar stripes animate from left to right
- Smooth width transitions (0.3s ease)
- Loading spinner on initial connection test

### Responsive Design:
- On mobile: Stats show 2x2 grid instead of 4x1
- Modal width: 90% on mobile, 600px max on desktop
- Text truncates if > 50 characters
- Error list scrolls if > 200px height

## Accessibility

- ARIA labels on all interactive elements
- Keyboard navigation support (Tab, Enter, Escape)
- High contrast ratios (WCAG AA compliant)
- Focus states on all buttons
- Screen reader friendly progress announcements
