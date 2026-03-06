# Disconnection System - Quick Reference

## URLs

### Web Dashboard
```
Create Orders:        http://localhost/WD/disconnection
Manage Assignments:   http://localhost/WD/disconnection/assignments
```

### API Endpoints
```
GET  /api/disconnector/assignments?disconnector_id={id}
GET  /api/disconnector/stats?disconnector_id={id}
GET  /api/disconnector/orders
POST /api/disconnector/assignments/status
```

## Database

### Table: disconnection_orders
```sql
-- Check if table exists
SHOW TABLES LIKE 'disconnection_orders';

-- Count orders
SELECT COUNT(*) FROM disconnection_orders;

-- See recent orders
SELECT id, account_no, account_name, status, disconnector_id 
FROM disconnection_orders 
ORDER BY created_at DESC LIMIT 10;

-- Check status distribution
SELECT status, COUNT(*) FROM disconnection_orders GROUP BY status;

-- See orders for specific disconnector
SELECT * FROM disconnection_orders WHERE disconnector_id = {id};
```

## Commands

### Laravel
```bash
# Run migration
php artisan migrate

# Check migration status
php artisan migrate:status

# Create disconnector user (from tinker)
php artisan tinker
> User::create(['name' => 'John Disconnector', 'email' => 'john@disc.com', 'password' => Hash::make('pass'), 'role' => 'disconnector'])

# Clear routes cache
php artisan route:clear

# Check routes
php artisan route:list | grep disconnector
```

## Files Changed/Created

### New Files
```
app/Models/DisconnectionOrder.php
app/Http/Controllers/Api/DisconnectorApiController.php
database/migrations/2026_01_01_000000_create_disconnection_orders_table.php
resources/views/disconnection/assignments.blade.php
DISCONNECTION_SETUP.md
DISCONNECTION_MOBILE_INTEGRATION.md
DISCONNECTION_SYSTEM.md
DISCONNECTION_IMPLEMENTATION.md
```

### Modified Files
```
app/Http/Controllers/DisconnectionController.php
routes/api.php
routes/web.php
resources/views/disconnection/index.blade.php
```

## Common Tasks

### Create Disconnection Orders

**URL**: `POST /disconnection/save-and-assign`

```php
$data = [
    'consumer_ids' => [1, 2, 3],
    'disconnection_date' => '2026-01-15',
    'assign_to' => 2  // optional disconnector ID
];
```

### View Orders

**URL**: `GET /disconnection/assignments`

Filters available:
- status: pending|assigned|in-progress|disconnected|reconnected
- zone: A1, A2, etc.
- disconnector_id: user ID

### Update Order Status

**API**: `POST /api/disconnector/assignments/status`

```json
{
  "order_id": 1,
  "status": "disconnected",
  "notes": "Service disconnected 2026-01-15"
}
```

## Status Values

| Status | Meaning | Who Sets |
|--------|---------|----------|
| pending | Created, not assigned | System (admin) |
| assigned | Assigned to disconnector | Admin/Web |
| in-progress | Disconnector working | Mobile app |
| disconnected | Service cut off | Mobile app |
| reconnected | Service restored | Mobile app |
| cancelled | Order cancelled | Admin/Web |

## API Response Examples

### Get Assignments
```json
{
  "success": true,
  "assignments": [
    {
      "id": 1,
      "account_no": "HWD-001-001",
      "account_name": "John Doe",
      "address": "123 Main St",
      "zone_code": "A1",
      "total_outstanding": 5000.00,
      "disconnection_date": "2026-01-15",
      "status": "assigned"
    }
  ],
  "count": 1
}
```

### Update Status Response
```json
{
  "success": true,
  "message": "Status updated successfully",
  "order": {
    "id": 1,
    "status": "disconnected",
    "disconnected_at": "2026-01-15 10:30:00"
  }
}
```

### Get Stats
```json
{
  "success": true,
  "stats": {
    "total_assigned": 10,
    "assigned": 5,
    "in_progress": 2,
    "disconnected": 2,
    "reconnected": 1,
    "total_outstanding": 25000.00
  }
}
```

## Troubleshooting

### Table Not Found
```bash
php artisan migrate
```

### API Endpoint Not Working
```bash
php artisan route:clear
php artisan route:cache
```

### Mobile App Not Fetching
- Check `WD_App/learningrn/config/api.js`
- Verify API_BASE_URL
- Check network connectivity
- Verify disconnector_id parameter

### No Orders Showing in Web
- Check if migration ran: `php artisan migrate:status`
- Query database: `SELECT COUNT(*) FROM disconnection_orders;`
- Check filters - make sure no filters are restricting view

## Integration Points

### Web → Database
```
DisconnectionController → DisconnectionOrder Model → disconnection_orders table
```

### Web → Mobile
```
DisconnectionController (save)
         ↓
DisconnectionOrder (database)
         ↓
DisconnectorApiController (API)
         ↓
Mobile App (fetch & display)
```

### Mobile → Database
```
DisconnectorAssignments.js (mobile app)
         ↓
disconnectorAPI.updateAssignmentStatus()
         ↓
DisconnectorApiController@updateAssignmentStatus
         ↓
DisconnectionOrder.update()
         ↓
disconnection_orders table
```

## Performance Tips

1. **Indexes** - Already on:
   - consumer_id (for quick lookups)
   - disconnector_id (for assignments)
   - zone_code (for filtering)
   - status (for filtering)

2. **Query Optimization** - Uses eager loading:
   ```php
   DisconnectionOrder::with(['consumer', 'disconnector'])->get();
   ```

3. **Pagination** - Web dashboard uses 20 per page

## Mobile App Integration

The mobile app's `DisconnectorAssignments.js` component:
1. Loads assignments via `disconnectorAPI.getAssignments()`
2. Displays in list format
3. Allows status update via `updateAssignmentStatus()`
4. Falls back to cached data offline
5. Auto-refreshes when resumed

## Future Enhancements

- [ ] GPS location tracking
- [ ] Photo evidence upload
- [ ] Push notifications
- [ ] SMS alerts
- [ ] Automated reconnection
- [ ] Bulk payment processing
- [ ] Dashboard analytics
- [ ] Advanced filtering
- [ ] Export reports
- [ ] Audit trail

## Key Metrics Tracked

- Total orders created
- Orders by status
- Outstanding amount by disconnector
- Completion rate
- Average resolution time (disconnected_at - assigned_at)
- Reconnection rate

## User Roles

- **Admin**: Create orders, assign, manage all
- **Disconnector**: View assigned, update status
- **Manager**: View reports, analytics
- **Customer**: View disconnection notices (planned)

## Testing Checklist

- [ ] Migration successful
- [ ] Table created in database
- [ ] Web page loads at /disconnection
- [ ] Can create orders
- [ ] Can assign to disconnector
- [ ] API endpoint returns data
- [ ] Mobile app sees assignments
- [ ] Can update status in mobile app
- [ ] Status updates show in web dashboard
- [ ] Filters work correctly
- [ ] Pagination works

## Support Resources

1. **Setup Guide**: `DISCONNECTION_SETUP.md`
2. **API Docs**: `DISCONNECTION_MOBILE_INTEGRATION.md`
3. **Architecture**: `DISCONNECTION_SYSTEM.md`
4. **Implementation Details**: `DISCONNECTION_IMPLEMENTATION.md`

## Quick Links

- Laravel Docs: https://laravel.com/docs
- React Native: https://reactnative.dev
- Water District: Internal system
- Database: localhost/phpmyadmin

## Notes

- All timestamps in UTC
- Amounts in PHP Pesos (₱)
- Zones follow water district mapping
- 3+ unpaid months = eligible for disconnection
- Disconnection date = 7 days by default (configurable)
