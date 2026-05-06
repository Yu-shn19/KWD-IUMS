# Combining WDMS Template with Tailwind CSS

## Overview
This guide explains how to use the WDMS (Ruang Admin) Bootstrap template alongside Tailwind CSS utility classes in your Laravel project.

## Structure

### File Locations
- **WDMS Template**: `public/WDMS/`
- **CSS**: `public/WDMS/css/ruang-admin.min.css`
- **JavaScript**: `public/WDMS/js/ruang-admin.min.js`
- **Bootstrap**: `public/WDMS/vendor/bootstrap/`
- **Font Awesome**: `public/WDMS/vendor/fontawesome-free/`

## Implementation

### 1. Header with Both Frameworks

**File**: `resources/views/partials/header-tailwind.blade.php`

```html
<head>
  <!-- WDMS/Bootstrap Styles -->
  <link href="{{url('WDMS/vendor/fontawesome-free/css/all.min.css')}}" rel="stylesheet">
  <link href="{{url('WDMS/vendor/bootstrap/css/bootstrap.min.css')}}" rel="stylesheet">
  <link href="{{url('WDMS/css/ruang-admin.min.css')}}" rel="stylesheet">
  
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      corePlugins: {
        preflight: false, // IMPORTANT: Prevents Tailwind from overriding Bootstrap
      }
    }
  </script>
</head>
```

### 2. Page Structure

```blade
<!DOCTYPE html>
<html lang="en">

@include('partials.header-tailwind')

<body id="page-top">
    <div id="wrapper">
        <!-- Sidebar (Bootstrap/WDMS) -->
        @include('partials.sidebar')
        
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <!-- Navbar (Bootstrap/WDMS) -->
                @include('partials.navbar')
                
                <!-- Content Area (Can use both Bootstrap and Tailwind) -->
                <div class="container-fluid" id="container-wrapper">
                    
                    <!-- Bootstrap Card -->
                    <div class="card mb-4">
                        <div class="card-header">Title</div>
                        <div class="card-body">Content</div>
                    </div>
                    
                    <!-- Tailwind Utilities -->
                    <div class="bg-white rounded-lg shadow-lg p-6">
                        <h3 class="text-lg font-bold text-gray-700">Tailwind Styled</h3>
                        <div class="flex space-x-4 mt-4">
                            <button class="bg-blue-500 text-white px-4 py-2 rounded">Button</button>
                        </div>
                    </div>
                    
                </div>
            </div>
            
            <!-- Footer -->
            @include('partials.footer')
        </div>
    </div>
</body>
</html>
```

## Usage Guide

### When to Use Bootstrap (WDMS)
Use Bootstrap for:
- ✅ Main layout structure (`container-fluid`, `row`, `col`)
- ✅ Navigation (sidebar, navbar)
- ✅ Bootstrap components (cards, modals, dropdowns)
- ✅ Forms (`form-control`, `form-group`)
- ✅ Buttons with Bootstrap styling (`btn btn-primary`)
- ✅ Tables (`table`, `table-striped`)

### When to Use Tailwind
Use Tailwind for:
- ✅ Custom utility classes (`flex`, `grid`, `space-x-4`)
- ✅ Modern styling (`rounded-lg`, `shadow-lg`)
- ✅ Responsive design (`md:flex`, `lg:grid-cols-3`)
- ✅ Colors (`bg-blue-500`, `text-gray-700`)
- ✅ Spacing (`p-4`, `m-2`, `space-y-3`)
- ✅ Custom animations and transitions

## Examples

### Example 1: Mixed Bootstrap & Tailwind Card

```html
<div class="card mb-4 shadow-lg">
    <div class="card-header bg-gradient-to-r from-blue-600 to-blue-700 text-white">
        <h5 class="mb-0 font-bold">Mixed Styling</h5>
    </div>
    <div class="card-body">
        <div class="flex space-x-4">
            <button class="btn btn-primary">Bootstrap Button</button>
            <button class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                Tailwind Button
            </button>
        </div>
    </div>
</div>
```

### Example 2: Responsive Grid with Both

```html
<!-- Bootstrap container with Tailwind utilities -->
<div class="container-fluid">
    <div class="row">
        <div class="col-md-6">
            <!-- Tailwind card -->
            <div class="bg-white rounded-lg shadow p-4">
                <h3 class="text-lg font-semibold text-gray-700">Card 1</h3>
                <p class="text-gray-600 text-sm">Content here</p>
            </div>
        </div>
        <div class="col-md-6">
            <!-- Bootstrap card -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Card 2</h5>
                    <p class="card-text">Content here</p>
                </div>
            </div>
        </div>
    </div>
</div>
```

### Example 3: Table with Mixed Styling

```html
<div class="overflow-x-auto"> <!-- Tailwind utility -->
    <table class="table table-bordered table-hover"> <!-- Bootstrap classes -->
        <thead class="bg-gray-100"> <!-- Tailwind background -->
            <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-600"> <!-- Tailwind utilities -->
                    Column 1
                </th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200"> <!-- Tailwind utilities -->
            <tr class="hover:bg-gray-50"> <!-- Tailwind hover -->
                <td class="px-4 py-2">Data</td>
            </tr>
        </tbody>
    </table>
</div>
```

## Important Configuration

### Disable Tailwind Preflight (Required!)
```javascript
tailwind.config = {
  corePlugins: {
    preflight: false, // This prevents Tailwind from resetting Bootstrap styles
  }
}
```

### Optional: Add Prefix to Avoid Conflicts
```javascript
tailwind.config = {
  prefix: 'tw-', // Now use tw-flex, tw-bg-blue-500, etc.
  corePlugins: {
    preflight: false,
  }
}
```

## Class Naming Conflicts to Watch

### Potential Conflicts
Both frameworks have these classes:
- `.container` - Use Bootstrap's or Tailwind's container
- `.hidden` - Bootstrap and Tailwind have different implementations
- `.visible` - Same as above

### Solutions
1. **Be specific**: Use `.container-fluid` (Bootstrap) or `.tw-container` (if using prefix)
2. **Choose one**: Decide which framework handles each component
3. **Test thoroughly**: Check responsive behavior

## Best Practices

1. **Layout**: Use Bootstrap for main structure (sidebar, navbar, grid)
2. **Components**: Use Bootstrap components (cards, modals, dropdowns)
3. **Utilities**: Use Tailwind for spacing, colors, typography
4. **Custom Design**: Use Tailwind for modern, custom styling
5. **Consistency**: Don't mix padding/margin approaches in same element

## Example Pages in Your Project

### Consumer Page
- **Layout**: Bootstrap (sidebar, navbar from WDMS)
- **Content Cards**: Mixed (Bootstrap cards with Tailwind utilities)
- **Tables**: Bootstrap tables with Tailwind styling
- **Buttons**: Bootstrap button classes with Tailwind colors

### Ledger Page
- **Structure**: Bootstrap container-fluid
- **Table**: Bootstrap table with Tailwind text utilities
- **Filters**: Bootstrap form controls with Tailwind spacing

### Charts Page
- **Layout**: Bootstrap grid
- **Cards**: Tailwind cards for statistics
- **Charts**: Chart.js with Tailwind styling around them

## Troubleshooting

### Issue: Styles not applying
**Solution**: Check if Tailwind's preflight is disabled

### Issue: Bootstrap buttons look different
**Solution**: Make sure WDMS CSS loads before Tailwind

### Issue: Responsive breakpoints conflict
**Solution**: Use Bootstrap breakpoints (sm, md, lg, xl) OR Tailwind's (sm, md, lg, xl, 2xl)

### Issue: CDN loading slowly
**Solution**: Consider installing Tailwind via npm for production

## Performance Tips

1. **Production**: Install Tailwind via npm and build only used classes
2. **Purge**: Configure PurgeCSS to remove unused Tailwind classes
3. **Minify**: Use minified versions of both frameworks
4. **Combine**: Consider merging custom CSS into one file

## Useful Resources

- [WDMS Template](https://github.com/indrijunanda/RuangAdmin)
- [Tailwind CSS Docs](https://tailwindcss.com/docs)
- [Bootstrap 4 Docs](https://getbootstrap.com/docs/4.6/)
- [Tailwind with Bootstrap Guide](https://tailwindcss.com/docs/configuration#core-plugins)

---

**Created for GuihingWD-IUMS Project**

