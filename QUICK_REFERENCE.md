# Quick Reference: Bootstrap vs Tailwind Classes

## Common UI Elements - Class Comparison

### Containers & Layout

| Purpose | Bootstrap (WDMS) | Tailwind |
|---------|------------------|----------|
| Container | `.container-fluid` | `.container` |
| Row | `.row` | `.flex` or `.grid` |
| Column | `.col-md-6` | `.w-1/2` or `.grid-cols-2` |
| Flexbox | `.d-flex` | `.flex` |
| Grid | N/A | `.grid` |

### Spacing

| Purpose | Bootstrap | Tailwind |
|---------|-----------|----------|
| Padding All | `.p-3` | `.p-4` |
| Padding Top | `.pt-3` | `.pt-4` |
| Margin All | `.m-3` | `.m-4` |
| Margin Bottom | `.mb-4` | `.mb-4` |
| Space Between | N/A | `.space-x-4`, `.space-y-4` |

### Text & Typography

| Purpose | Bootstrap | Tailwind |
|---------|-----------|----------|
| Text Size | `.h1`, `.h5` | `.text-xl`, `.text-sm` |
| Text Color | `.text-primary` | `.text-blue-600` |
| Text Weight | `.font-weight-bold` | `.font-bold` |
| Text Align | `.text-center` | `.text-center` |
| Text Transform | `.text-uppercase` | `.uppercase` |

### Colors

| Purpose | Bootstrap | Tailwind |
|---------|-----------|----------|
| Background | `.bg-primary` | `.bg-blue-500` |
| Text Color | `.text-danger` | `.text-red-600` |
| Border Color | `.border-primary` | `.border-blue-500` |

### Buttons

| Purpose | Bootstrap | Tailwind |
|---------|-----------|----------|
| Primary Button | `.btn .btn-primary` | `.bg-blue-500 .text-white .px-4 .py-2 .rounded` |
| Small Button | `.btn .btn-sm` | `.text-sm .px-3 .py-1` |
| Button Sizes | `.btn-lg`, `.btn-sm` | `.text-lg`, `.text-sm` with custom padding |

### Cards

| Purpose | Bootstrap | Tailwind |
|---------|-----------|----------|
| Card Container | `.card` | `.bg-white .rounded-lg .shadow` |
| Card Header | `.card-header` | `.px-4 .py-3 .border-b` |
| Card Body | `.card-body` | `.p-4` |

### Tables

| Purpose | Bootstrap | Tailwind |
|---------|-----------|----------|
| Basic Table | `.table` | `.min-w-full` |
| Bordered | `.table-bordered` | `.border .border-collapse` |
| Striped | `.table-striped` | `.divide-y .divide-gray-200` |
| Hover | `.table-hover` | `.hover:bg-gray-50` |

### Forms

| Purpose | Bootstrap | Tailwind |
|---------|-----------|----------|
| Form Group | `.form-group` | `.mb-4` |
| Input | `.form-control` | `.border .rounded .px-3 .py-2 .w-full` |
| Label | `.form-label` | `.block .text-sm .font-medium` |
| Select | `.form-select` | `.border .rounded .px-3 .py-2` |

### Display & Visibility

| Purpose | Bootstrap | Tailwind |
|---------|-----------|----------|
| Show/Hide | `.d-none`, `.d-block` | `.hidden`, `.block` |
| Responsive Hide | `.d-md-none` | `.md:hidden` |
| Flex Display | `.d-flex` | `.flex` |
| Grid Display | N/A | `.grid` |

### Borders & Shadows

| Purpose | Bootstrap | Tailwind |
|---------|-----------|----------|
| Border | `.border` | `.border` |
| Border Color | `.border-primary` | `.border-blue-500` |
| Rounded | `.rounded` | `.rounded` or `.rounded-lg` |
| Shadow | `.shadow` | `.shadow-lg`, `.shadow-md` |

### Positioning

| Purpose | Bootstrap | Tailwind |
|---------|-----------|----------|
| Position Relative | `.position-relative` | `.relative` |
| Position Absolute | `.position-absolute` | `.absolute` |
| Position Fixed | `.fixed-top` | `.fixed .top-0` |
| Position Sticky | `.sticky-top` | `.sticky .top-0` |

---

## Combined Examples

### Bootstrap Card with Tailwind Utilities
```html
<div class="card mb-4 shadow-lg rounded-lg">
    <div class="card-header bg-blue-600 text-white">
        <h5 class="mb-0 font-bold">Title</h5>
    </div>
    <div class="card-body p-4">
        <p class="text-gray-600 text-sm">Content</p>
    </div>
</div>
```

### Bootstrap Grid with Tailwind Spacing
```html
<div class="container-fluid">
    <div class="row space-y-4">
        <div class="col-md-6">
            <div class="bg-white p-4 rounded shadow">Content</div>
        </div>
    </div>
</div>
```

### Bootstrap Table with Tailwind Styling
```html
<table class="table table-hover">
    <thead class="bg-gray-100">
        <tr>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-600">Column</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-200">
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-2">Data</td>
        </tr>
    </tbody>
</table>
```

### Bootstrap Button with Tailwind Effects
```html
<button class="btn btn-primary shadow-md hover:shadow-lg transition-all">
    Click Me
</button>
```

---

## Pro Tips

1. **Use Bootstrap for structure** (rows, columns, containers)
2. **Use Tailwind for styling** (colors, spacing, shadows)
3. **Mix both in the same element** - they work together!
4. **Bootstrap components** (modals, dropdowns) + **Tailwind utilities** = Perfect combo
5. **Always disable Tailwind preflight** when combining with Bootstrap

## Your Current Setup

✅ Bootstrap 4 (WDMS Template)
✅ Tailwind CSS via CDN
✅ Font Awesome Icons
✅ Preflight disabled to prevent conflicts

**Ready to use both frameworks together!**

