# Disconnection Management System

## Overview

The Disconnection Management System is an integrated solution for managing consumer disconnections across web dashboard and mobile app. It allows:

- **Admin/Manager**: Create disconnection lists, manage orders, assign to disconnectors
- **Disconnector**: Receive assignments on mobile app, track progress, update status
- **Real-time Sync**: Orders sync instantly between web dashboard and mobile app

## Features

### Web Dashboard
- ✅ Filter consumers by zone
- ✅ Identify consumers with 3+ consecutive unpaid months
- ✅ Generate professional disconnection notices
- ✅ Save orders to database
- ✅ Assign orders to specific disconnectors
- ✅ Track disconnection status (pending, assigned, in-progress, disconnected, reconnected)
- ✅ View assignment history
- ✅ Export/Print notices

### Mobile App
- ✅ Real-time assignment notifications
- ✅ View assigned disconnection tasks
- ✅ Customer details and outstanding balance
- ✅ Mark as in-progress, disconnected, reconnected
- ✅ Add field notes
- ✅ Track completion stats
- ✅ Offline support

## System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    WEB DASHBOARD                                │
│  (/disconnection, /disconnection/assignments)                   │
│                                                                 │
│  1. Create List → 2. Save Orders → 3. Assign → 4. Track Status │
└────────────────┬─────────────────────────────────────────────────┘
                 │
                 ├──→ Database: disconnection_orders
                 │
                 └──→ API Endpoints (/api/disconnector/*)
                      │
                      └──→ Mobile App: DisconnectorAssignments
                          │
                          ├─ Fetch assignments
                          ├─ Update status
                          └─ Sync completion
```

## Database Schema

### disconnection_orders Table

| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary key |
| consumer_id | INT | Consumer reference |
| disconnector_id | INT | Assigned disconnector |
| account_no | STRING | Consumer account number |
| account_name | STRING | Consumer name |
| address | STRING | Consumer address |
| zone_code | STRING | Water zone |
| meter_number | STRING | Meter ID |
| card_number | INT | Card/Route number |
| this_month_arrears | DECIMAL | Current month amount due |
| last_month_arrears | DECIMAL | Previous month amount due |
| others_ar | DECIMAL | Other arrears |
| total_outstanding | DECIMAL | Total owed |
| unpaid_months | INT | Count of unpaid months |
| oldest_unpaid_date | DATE | Oldest unpaid bill date |
| latest_unpaid_date | DATE | Most recent unpaid bill date |
| disconnection_date | DATE | Scheduled disconnection date |
| status | ENUM | pending/assigned/in-progress/disconnected/reconnected/cancelled |
| assigned_at | TIMESTAMP | When assigned to disconnector |
| disconnected_at | TIMESTAMP | When service was cut |
| reconnected_at | TIMESTAMP | When service was restored |
| notes | TEXT | Field notes |
| created_at | TIMESTAMP | Record creation time |
| updated_at | TIMESTAMP | Last update time |

## API Reference

### 1. Get Disconnector Assignments

```
GET /api/disconnector/assignments?disconnector_id={id}
```

Returns list of assigned disconnection tasks for a specific disconnector.

**Response**:
```json
{
  "success": true,
  "assignments": [
    {
      "id": 1,
      "account_no": "HWD-001-123",
      "account_name": "Juan Dela Cruz",
      "address": "123 Main St, Hagonoy",
      "zone_code": "A1",
      "total_outstanding": 5000.00,
      "unpaid_months": 3,
      "disconnection_date": "2026-01-15",
      "status": "assigned",
      "this_month_arrears": 2000,
      "last_month_arrears": 1500,
      "others_ar": 1500
    }
  ],
  "count": 1
}
```

### 2. Update Assignment Status

```
POST /api/disconnector/assignments/status
```

Update the status of a disconnection assignment.

**Body**:
```json
{
  "order_id": 1,
  "status": "disconnected",
  "notes": "Service disconnected. Meter seal applied."
}
```

**Status Values**:
- `in-progress`: Currently working on this
- `disconnected`: Service has been cut
- `reconnected`: Service has been restored
- `cancelled`: Order cancelled

**Response**:
```json
{
  "success": true,
  "message": "Status updated successfully",
  "order": {
    "id": 1,
    "status": "disconnected",
    "disconnected_at": "2026-01-15 10:30:00",
    "notes": "Service disconnected. Meter seal applied."
  }
}
```

### 3. Get Disconnector Stats

```
GET /api/disconnector/stats?disconnector_id={id}
```

Get statistics for disconnector dashboard.

**Response**:
```json
{
  "success": true,
  "stats": {
    "total_assigned": 10,
    "pending": 0,
    "assigned": 5,
    "in_progress": 2,
    "disconnected": 2,
    "reconnected": 1,
    "cancelled": 0,
    "total_outstanding": 25000.00
  }
}
```

### 4. Get All Orders

```
GET /api/disconnector/orders?status={status}&zone={zone}&disconnector_id={id}
```

Get orders with optional filters.

### 5. Get Single Order

```
GET /api/disconnector/orders/{orderId}
```

Get detailed information about a specific order.

## File Structure

```
app/
├── Models/
│   └── DisconnectionOrder.php          # Model for disconnection orders
├── Http/Controllers/
│   ├── DisconnectionController.php      # Web dashboard controller
│   └── Api/
│       └── DisconnectorApiController.php # API controller
database/
└── migrations/
    └── 2026_01_01_000000_create_disconnection_orders_table.php
resources/
└── views/disconnection/
    ├── index.blade.php                 # Main disconnection page
    ├── notice.blade.php                # Notice preview
    ├── print.blade.php                 # Print version
    └── assignments.blade.php           # Assignments management
routes/
├── web.php                             # Web routes
└── api.php                             # API routes
WD_App/learningrn/
├── DisconnectorAssignments.js          # Mobile component
└── services/
    └── api.js                          # API client
```

## Setup Instructions

### Prerequisites
- Laravel 11+
- PHP 8.1+
- MySQL/MariaDB
- React Native app

### Installation

1. **Run Migration**
```bash
php artisan migrate
```

2. **Create Disconnector Users**
   - Go to admin dashboard
   - Create users with role = "disconnector"

3. **Verify API Routes**
```bash
php artisan route:list | grep disconnector
```

4. **Update Mobile Config**
   - Edit `WD_App/learningrn/config/api.js`
   - Set correct API base URL

### Testing

```bash
# Test API
curl "http://localhost/WD/api/disconnector/assignments?disconnector_id=2"

# Test web dashboard
# Open: http://localhost/WD/disconnection
```

## Usage Workflow

### Web Dashboard

1. **Access Disconnection Page**
   - Go to `/disconnection`
   - You see list of consumers with unpaid bills

2. **Filter and Select**
   - Filter by zone (optional)
   - Select consumers requiring disconnection
   - Set disconnection date

3. **Save & Assign**
   - Click "Save & Send to Mobile App"
   - Optionally assign to specific disconnector
   - Orders saved to database

4. **View Assignments**
   - Go to `/disconnection/assignments`
   - See all orders and their status
   - Reassign if needed

### Mobile App

1. **View Assignments**
   - Open Disconnector Dashboard
   - See list of assigned tasks

2. **Work on Assignment**
   - Tap assignment to view details
   - Note customer info and amount due
   - Mark as "In Progress"

3. **Complete Disconnection**
   - Go to customer location
   - Perform disconnection
   - Return to app
   - Mark as "Disconnected"
   - Add notes if needed

4. **Track Progress**
   - View stats dashboard
   - See completion percentage
   - Monitor outstanding amount

## Status Workflow

```
┌─────────┐
│ Pending │ (Not assigned yet)
└────┬────┘
     │ (Assign to disconnector)
     ↓
┌──────────┐
│ Assigned │ (Ready for work)
└────┬─────┘
     │ (Start work)
     ↓
┌──────────────┐
│ In-Progress  │ (Disconnector working)
└────┬─────────┘
     │ (Work completed)
     ↓
┌─────────────┐
│ Disconnected│ (Service cut off)
└────┬────────┘
     │ (If reconnection requested)
     ↓
┌───────────────┐
│ Reconnected   │ (Service restored)
└───────────────┘

OR at any point:
     │
     ↓
┌──────────┐
│ Cancelled│ (Order cancelled)
└──────────┘
```

## Error Handling

### Common Errors and Solutions

| Error | Cause | Solution |
|-------|-------|----------|
| Table not found | Migration not run | `php artisan migrate` |
| API returns 404 | Routes not configured | Check `routes/api.php` |
| No data in app | Wrong API URL | Check mobile `config/api.js` |
| Assignment not saving | Invalid user ID | Verify user exists and has "disconnector" role |

## Security Considerations

- ✅ Role-based access control (admin/disconnector)
- ✅ Database validation
- ⚠️ API endpoints currently public (add authentication for production)
- ✅ Input validation on all forms
- ✅ Soft delete support (can add)

## Performance

- Database indexes on frequently searched fields
- Efficient relationship loading with eager loading
- Pagination on assignment list (20 per page)
- Caching support (can add)

## Future Enhancements

- GPS tracking of disconnections
- Photo/evidence upload
- Offline mode with sync
- Push notifications
- SMS alerts to customers
- Bulk payment processing
- Automatic reconnection scheduling
- Dashboard analytics

## Support & Troubleshooting

See [DISCONNECTION_SETUP.md](DISCONNECTION_SETUP.md) and [DISCONNECTION_MOBILE_INTEGRATION.md](DISCONNECTION_MOBILE_INTEGRATION.md) for detailed documentation.

## License

Part of the Water District Management System (WDMS)
