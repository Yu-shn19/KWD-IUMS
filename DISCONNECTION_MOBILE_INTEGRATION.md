# Disconnection Management System - Mobile App Integration Guide

## Overview

This guide explains how the disconnection management system works and how it integrates with the mobile app for disconnectors.

## System Architecture

### Web Dashboard Flow
1. **Create List**: Admin/Manager creates a list of consumers eligible for disconnection
2. **Save Orders**: Orders are saved to `disconnection_orders` table
3. **Assign to Disconnector**: Orders are assigned to specific disconnectors
4. **Mobile App**: Disconnectors receive assignments on their mobile app

### Database Tables

#### disconnection_orders
```
id
consumer_id (FK -> consumer_zone)
disconnector_id (FK -> users)
account_no
account_name
address
zone_code
meter_number
card_number
this_month_arrears (decimal)
last_month_arrears (decimal)
others_ar (decimal)
total_outstanding (decimal)
unpaid_months (int)
oldest_unpaid_date (date)
latest_unpaid_date (date)
disconnection_date (date)
status (pending|assigned|in-progress|disconnected|reconnected|cancelled)
assigned_at (timestamp)
disconnected_at (timestamp)
reconnected_at (timestamp)
notes (text)
created_at
updated_at
```

## Web Dashboard Features

### 1. Create Disconnection List
**Route**: `/disconnection`
**Controller**: `DisconnectionController@index`

- Filter consumers by zone
- View list of consumers with 3+ consecutive unpaid months
- Select consumers for disconnection notice

### 2. Generate Notice Preview
**Route**: `POST /disconnection/generate-notice`
**Controller**: `DisconnectionController@generateNotice`

- Preview disconnection notice before printing
- Shows consumer details and outstanding balance
- Calculate statement of account

### 3. Save & Assign Orders
**Route**: `POST /disconnection/save-and-assign`
**Controller**: `DisconnectionController@saveAndAssign`

**Parameters**:
```json
{
  "consumer_ids": [1, 2, 3],
  "disconnection_date": "2026-01-15",
  "assign_to": 5  // disconnector user ID (optional)
}
```

**Response**:
- Creates DisconnectionOrder records
- Assigns to disconnector if specified
- Updates status to "assigned"

### 4. Manage Assignments
**Route**: `/disconnection/assignments`
**Controller**: `DisconnectionController@assignments`

- View all disconnection orders
- Filter by status, zone, disconnector
- Reassign orders to different disconnectors
- Track completion status

## Mobile App API Endpoints

### Base URL
```
http://your-server/api/disconnector
```

### 1. Get Disconnector Assignments
```
GET /api/disconnector/assignments?disconnector_id={id}
```

**Response**:
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
      "meter_number": "M12345",
      "card_number": 1,
      "total_outstanding": 5000.00,
      "unpaid_months": 3,
      "disconnection_date": "2026-01-15",
      "status": "assigned",
      "type": "disconnection",
      "assignment_type": "disconnection"
    }
  ],
  "count": 1
}
```

### 2. Update Assignment Status
```
POST /api/disconnector/assignments/status
```

**Body**:
```json
{
  "order_id": 1,
  "status": "in-progress|disconnected|reconnected|cancelled",
  "notes": "optional notes"
}
```

**Status Values**:
- `in-progress`: Disconnector is working on this
- `disconnected`: Service has been disconnected
- `reconnected`: Service has been restored
- `cancelled`: Order was cancelled

### 3. Get Disconnector Stats
```
GET /api/disconnector/stats?disconnector_id={id}
```

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

### 5. Get Single Order
```
GET /api/disconnector/orders/{orderId}
```

### 6. Automatic cancellation when consumer pays

Whenever a consumer who has an active disconnection order (status `pending`, `assigned`, or `in-progress`) has a payment recorded in **consumer_payments** (with `paid_at` set), the system automatically:

1. Cancels all such orders for that consumer (even if their balance is not fully cleared).
2. Sets `status` to `cancelled` and `notes` to: *"Cancelled - consumer paid on YYYY-MM-DD. Do not disconnect."* (using the payment’s `paid_at` date).

This runs in **BillingProcessController** when a payment is saved (e.g. via `markDownloadedReadingPaid`). The disconnector will no longer see those consumers in **Get Disconnector Assignments** (they are excluded).

### 7. Get Cancelled Due to Payment (notifications)

So the disconnector is explicitly informed not to disconnect consumers who have paid, the app can call:

```
GET /api/disconnector/cancelled-due-to-payment?disconnector_id={id}&since={datetime}
```

- **disconnector_id** (required): User ID of the disconnector
- **since** (optional): ISO datetime; only orders cancelled after this time. Default: last 30 days

**Response**:
```json
{
  "success": true,
  "cancelled_due_to_payment": [
    {
      "id": 5,
      "account_no": "HWD-001-005",
      "account_name": "Jane Smith",
      "address": "456 Oak St",
      "zone_code": "A1",
      "status": "cancelled",
      "notes": "Cancelled - consumer paid on 2026-02-09. Do not disconnect."
    }
  ],
  "count": 1,
  "message": "Consumers who paid and were removed from your disconnection list."
}
```

The **Disconnector Dashboard** in the mobile app uses this endpoint on load/refresh, shows a “Do not disconnect – paid” section listing these consumers, and displays an **in-app notification (alert)** when any such consumers exist so the disconnector is explicitly told not to disconnect them.

## Mobile App Integration

### Disconnector Dashboard
Location: `WD_App/learningrn/DisconnectorDashboard.js`

**Features**:
- Summary stats (assigned, pending, in progress, completed)
- **Do not disconnect – paid**: When the API returns cancelled-due-to-payment entries (any payment in consumer_payments for that consumer while they were in disconnection_orders), a green notice lists those consumers so the disconnector does not disconnect them.
- **In-app notification**: When the dashboard loads and there are consumers who paid, an alert dialog is shown (“Do not disconnect”) so the disconnector is notified immediately.
- Refresh fetches latest assignments and cancelled-due-to-payment list

### DisconnectorAssignments Component
Location: `WD_App/learningrn/DisconnectorAssignments.js`

**Features**:
- Load assigned disconnection tasks (cancelled orders are not returned by the API)
- Filter by account number, zone
- Mark as in-progress
- Mark as disconnected
- Add notes
- View completion stats

**Key Methods**:
```javascript
loadAssignments()          // Fetch assignments from API
handleRefresh()            // Manually refresh assignments
handleDownload()           // Download routes/assignments
handleDisconnection()      // Mark as disconnected
handleReconnection()       // Mark as reconnected
```

### Data Flow
```
Mobile App
    ↓
DisconnectorAssignments.js
    ↓
disconnectorAPI.getAssignments()
    ↓
API: /api/disconnector/assignments
    ↓
Laravel: DisconnectorApiController@getAssignments
    ↓
Database: disconnection_orders table
```

## Setup Instructions

### 1. Run Migration
```bash
php artisan migrate
```

Creates the `disconnection_orders` table.

### 2. Create Disconnector Users
In the admin dashboard:
- Create user accounts with role = "disconnector"
- These users will be available for assignment

### 3. Test API Endpoint
```bash
curl "http://localhost/api/disconnector/assignments?disconnector_id=2"
```

### 4. Configure Mobile App
In `WD_App/learningrn/config/api.js`:
```javascript
const API_BASE_URL = 'http://your-xampp-server/WD/public/api';
```

## Usage Workflow

### For Admin/Manager

1. Go to `/disconnection`
2. Filter by zone (optional)
3. Select consumers for disconnection
4. Set disconnection date
5. (Optional) Select disconnector to assign
6. Click "Save & Send to Mobile App"
7. Or click "Generate Notice for Preview" to see details first

### For Disconnector (Mobile App)

1. Open mobile app dashboard
2. Go to "Disconnector Assignments" tab
3. View list of assigned disconnections
4. For each assignment:
   - View customer details
   - View outstanding amount
   - Click to mark as "In Progress"
   - Go to customer location and disconnect service
   - Return to app and mark as "Disconnected"
   - Add notes if needed
5. Stats update automatically

## Status Transitions

```
pending (not assigned)
   ↓
assigned (assigned to disconnector)
   ↓
in-progress (disconnector is working)
   ↓
disconnected (service cut off)
   ↓
reconnected (service restored) OR cancelled

cancelled (at any point)
```

## Error Handling

### Common Issues

1. **API Not Found**
   - Check Laravel migration ran: `php artisan migrate`
   - Verify routes in `routes/api.php`
   - Check DisconnectorApiController is in correct folder

2. **Mobile App Not Fetching Data**
   - Verify API_BASE_URL in mobile config
   - Check network connectivity
   - Verify disconnector_id is passed correctly
   - Check browser console for errors

3. **Orders Not Saving**
   - Check consumer_ids are valid
   - Verify user role is set to "disconnector" for assignees
   - Check database connection

## API Authentication

Currently, the API endpoints are public. For production:

1. Add middleware protection:
```php
Route::prefix('disconnector')->middleware('auth:api')->group(function () {
    // routes here
});
```

2. Implement token-based auth similar to reader API

## Future Enhancements

1. GPS location tracking
2. Photo evidence upload
3. Offline mode support
4. Push notifications for new assignments
5. Two-factor disconnection approval
6. Automatic reconnection scheduling
7. Bulk payment processing
8. SMS notifications to customers

## Testing

### Test API with cURL

```bash
# Get assignments for disconnector ID 2
curl "http://localhost/api/disconnector/assignments?disconnector_id=2"

# Update assignment status
curl -X POST "http://localhost/api/disconnector/assignments/status" \
  -H "Content-Type: application/json" \
  -d '{
    "order_id": 1,
    "status": "disconnected",
    "notes": "Service disconnected due to non-payment"
  }'

# Get stats
curl "http://localhost/api/disconnector/stats?disconnector_id=2"
```

## Support

For issues or questions:
1. Check mobile app console logs
2. Check Laravel logs: `storage/logs/laravel.log`
3. Verify database migration: `php artisan migrate:status`
4. Check user role: `SELECT * FROM users WHERE role='disconnector'`
