# Disconnection System Setup

## Quick Start

Follow these steps to set up the disconnection management system:

### Step 1: Run Database Migration

Open command line in the project root (`c:\xampp\htdocs\WD\`) and run:

```bash
php artisan migrate
```

This creates the `disconnection_orders` table.

### Step 2: Create Disconnector Users (Optional)

Go to your admin dashboard and create new users with:
- Role: `disconnector`
- Example: "John Disconnector" with username "john.disconnect"

### Step 3: Access the System

#### Web Dashboard:
- **Main Page**: `http://localhost/WD/disconnection`
- **Assignments**: `http://localhost/WD/disconnection/assignments`

#### Mobile App:
- The app will automatically show assignments on the Disconnector Dashboard
- Make sure `config/api.js` points to correct server URL

### Step 4: Test the System

1. Go to `/disconnection` on the web app
2. Select some consumers from a zone
3. Set a disconnection date
4. Optionally assign to a disconnector
5. Click "Save & Send to Mobile App"
6. Check `/disconnection/assignments` to see saved orders
7. On mobile app, refresh to see new assignments

### Step 5: Test API (Optional)

Test the API directly with cURL:

```bash
# Test if server is running
curl http://localhost/WD/api/test

# Get assignments (replace 2 with actual disconnector ID)
curl "http://localhost/WD/api/disconnector/assignments?disconnector_id=2"
```

## What Gets Created

### Database Table
- `disconnection_orders` - Stores all disconnection assignments

### API Endpoints
- `GET /api/disconnector/assignments` - Get assigned tasks
- `GET /api/disconnector/stats` - Get stats
- `GET /api/disconnector/orders` - Get all orders
- `POST /api/disconnector/assignments/status` - Update status

### Web Pages
- `/disconnection` - Create and manage disconnections
- `/disconnection/assignments` - View and assign orders

### Mobile Integration
- DisconnectorAssignments component will fetch from new API
- Updates automatically reflect in mobile app

## Troubleshooting

### Migration doesn't run
```bash
php artisan migrate:reset
php artisan migrate
```

### Table doesn't exist
- Check if migration ran: `php artisan migrate:status`
- If showing "pending", run `php artisan migrate`

### API returns 404
- Verify routes in `routes/api.php`
- Check DisconnectorApiController exists
- Clear routes cache: `php artisan route:clear`

### Mobile app doesn't fetch
- Check API_BASE_URL in mobile config
- Verify network connectivity
- Check browser console logs
- Verify disconnector_id parameter

## API Documentation

See [DISCONNECTION_MOBILE_INTEGRATION.md](DISCONNECTION_MOBILE_INTEGRATION.md) for complete API documentation.
