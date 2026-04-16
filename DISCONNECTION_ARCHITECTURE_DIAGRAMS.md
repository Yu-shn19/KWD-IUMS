# System Architecture Diagrams

## Complete System Flow

```
┌─────────────────────────────────────────────────────────────────────────┐
│                                                                         │
│                    DISCONNECTION MANAGEMENT SYSTEM                      │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘

                           ┌──────────────────┐
                           │   WEB BROWSER    │
                           └────────┬─────────┘
                                    │
                    ┌───────────────┼───────────────┐
                    │               │               │
                    ▼               ▼               ▼
        ┌──────────────────┐ ┌──────────────────┐ ┌──────────────────┐
        │ /disconnection   │ │    /disconnection│ │    /disconnection│
        │                  │ │    /assignments  │ │    /notice       │
        │ • Create List    │ │                  │ │                  │
        │ • Filter Zone    │ │ • Manage Orders  │ │ • Preview Notice │
        │ • Select Items   │ │ • Filter & Search│ │ • Print/Download │
        └────────┬─────────┘ └────────┬─────────┘ └────────┬─────────┘
                 │                    │                     │
                 └────────────────────┼─────────────────────┘
                                      │
                    ┌─────────────────▼─────────────────┐
                    │  DisconnectionController (Laravel)│
                    │  ├─ index()                        │
                    │  ├─ saveAndAssign()                │
                    │  ├─ assignments()                  │
                    │  └─ assignOrders()                 │
                    └─────────────────┬─────────────────┘
                                      │
                    ┌─────────────────▼─────────────────┐
                    │    DATABASE - MySQL/MariaDB        │
                    │                                   │
                    │  disconnection_orders Table:       │
                    │  ├─ id (PK)                        │
                    │  ├─ consumer_id (FK)               │
                    │  ├─ disconnector_id (FK)           │
                    │  ├─ account_no                     │
                    │  ├─ total_outstanding              │
                    │  ├─ status                         │
                    │  ├─ assigned_at                    │
                    │  ├─ disconnected_at                │
                    │  └─ [other fields...]              │
                    │                                   │
                    └─────────────────┬─────────────────┘
                                      │
                    ┌─────────────────▼─────────────────┐
                    │  API Controller                    │
                    │  (DisconnectorApiController)       │
                    │                                   │
                    │  Routes:                          │
                    │  ├─ GET  /assignments              │
                    │  ├─ GET  /stats                    │
                    │  ├─ GET  /orders                   │
                    │  └─ POST /assignments/status       │
                    └─────────────────┬─────────────────┘
                                      │
                    ┌─────────────────▼─────────────────┐
                    │   MOBILE APP - React Native       │
                    │                                   │
                    │  DisconnectorAssignments:         │
                    │  ├─ loadAssignments()              │
                    │  ├─ updateStatus()                 │
                    │  ├─ addNotes()                     │
                    │  └─ getStats()                     │
                    │                                   │
                    │  Network:                         │
                    │  • Fetch assignments              │
                    │  • Update status in real-time     │
                    │  • Offline caching                │
                    │  • Auto-sync when online          │
                    │                                   │
                    └────────────────────────────────────┘
```

## Data Flow Diagram

```
CREATE ORDERS FLOW:
═════════════════════════════════════════════════════════════════

    ┌─────────────────────┐
    │  Admin on Web       │
    │  /disconnection     │
    └──────────┬──────────┘
               │
               ▼
    ┌─────────────────────────────────┐
    │ 1. Filter by Zone (optional)     │
    │ 2. View Eligible Consumers       │
    │ 3. Select Multiple               │
    │ 4. Set Disconnection Date        │
    │ 5. Choose Disconnector (optional)│
    └──────────┬──────────────────────┘
               │
               ▼
    ┌─────────────────────────────────┐
    │ Choose Action:                  │
    │ A) Save & Send to Mobile App    │
    │ B) Generate Notice for Preview  │
    └──────────┬──────────────────────┘
               │
       ┌───────┴───────┐
       │               │
       ▼               ▼
    ┌──────────┐   ┌──────────────────────────┐
    │ Generate │   │ Save & Assign             │
    │  Notice  │   │ ─────────────────────────│
    │          │   │ 1. Validate Input        │
    │  Preview │   │ 2. Fetch Consumer Data   │
    │  & Print │   │ 3. Calculate Outstanding │
    │          │   │ 4. Create Orders         │
    │          │   │ 5. Set Status="assigned" │
    │          │   │ 6. Assign to Disconnector│
    │          │   │ 7. Save to Database      │
    │          │   └──────────┬───────────────┘
    │          │              │
    └──────────┘              ▼
                   ┌──────────────────────┐
                   │  Database Updated    │
                   │  Orders Saved        │
                   │  Status: "assigned"  │
                   └──────────┬───────────┘
                              │
                              ▼
                   ┌──────────────────────┐
                   │  Mobile App Syncs    │
                   │  Disconnector Gets   │
                   │  New Assignments     │
                   └──────────────────────┘


UPDATE STATUS FLOW:
═════════════════════════════════════════════════════════════════

    ┌───────────────────────┐
    │ Disconnector on Mobile│
    │ DisconnectorAssignment│
    │ s Component           │
    └──────────┬────────────┘
               │
               ▼
    ┌───────────────────────────────────────────┐
    │ 1. View Assignment List                   │
    │ 2. Tap Assignment                         │
    │ 3. View Customer Details & Amounts        │
    │ 4. Go to Location                         │
    │ 5. Perform Disconnection                  │
    │ 6. Return to App                          │
    └──────────┬──────────────────────────────┘
               │
               ▼
    ┌────────────────────────────────┐
    │ Mark as Disconnected           │
    │ ──────────────────────────────│
    │ 1. Tap "Mark Disconnected"     │
    │ 2. Enter Notes (optional)      │
    │ 3. Confirm                     │
    └──────────┬─────────────────────┘
               │
               ▼
    ┌────────────────────────────────┐
    │ API Call:                      │
    │ POST /api/disconnector/        │
    │ assignments/status             │
    │ ──────────────────────────────│
    │ {                              │
    │   "order_id": 1,               │
    │   "status": "disconnected",    │
    │   "notes": "..."               │
    │ }                              │
    └──────────┬─────────────────────┘
               │
               ▼
    ┌────────────────────────────────┐
    │ Laravel API Controller         │
    │ Validates & Updates            │
    │ Database                       │
    └──────────┬─────────────────────┘
               │
               ▼
    ┌────────────────────────────────┐
    │ Database Updated:              │
    │ • status = "disconnected"      │
    │ • disconnected_at = NOW()      │
    │ • notes = "..."                │
    └──────────┬─────────────────────┘
               │
               ▼
    ┌────────────────────────────────┐
    │ Admin Sees Live Update:        │
    │ /disconnection/assignments     │
    │ Status: DISCONNECTED (red)     │
    │ Time: 2026-01-15 10:30:00      │
    └────────────────────────────────┘
```

## Database Relationships

```
                        ┌──────────────────┐
                        │  consumer_zone   │
                        │  ──────────────  │
                        │  id (PK)         │
                        │  account_no      │
                        │  account_name    │
                        │  address1        │
                        │  zone_code       │
                        │  meter_number    │
                        │  sequence        │
                        └────────┬─────────┘
                                 │
                                 │ 1:N
                                 │ (FK: consumer_id)
                                 │
                        ┌────────▼─────────┐
                        │ disconnection_   │
                        │ orders           │
                        │ ──────────────── │
                        │ id (PK)          │
                        │ consumer_id(FK)  │
                        │ disconnector_id  │
                        │ account_no       │
                        │ account_name     │
                        │ total_outstanding│
                        │ unpaid_months    │
                        │ status           │
                        │ assigned_at      │
                        │ disconnected_at  │
                        │ reconnected_at   │
                        │ notes            │
                        │ created_at       │
                        │ updated_at       │
                        └────────┬─────────┘
                                 │
                                 │ 1:N
                                 │ (FK: disconnector_id)
                                 │
                        ┌────────▼──────────┐
                        │  users            │
                        │  ────────────────│
                        │  id (PK)          │
                        │  name             │
                        │  email            │
                        │  password         │
                        │  role             │
                        │  created_at       │
                        │  updated_at       │
                        └───────────────────┘
```

## Status Transition Diagram

```
                        START
                         │
                         ▼
                    ┌─────────────┐
                    │   PENDING   │ (Not assigned)
                    │   (Gray)    │
                    └──────┬──────┘
                           │
                           │ Admin assigns
                           │ to disconnector
                           │
                           ▼
                    ┌──────────────┐
                    │   ASSIGNED   │ (Ready for work)
                    │   (Blue)     │
                    └──────┬───────┘
                           │
                           │ Disconnector
                           │ starts work
                           │
                           ▼
                    ┌──────────────────┐
                    │  IN-PROGRESS     │ (Working on it)
                    │  (Yellow)        │
                    └──────┬───────────┘
                           │
                           │ Work completed
                           │ Service cut
                           │
                           ▼
                    ┌──────────────────┐
                    │  DISCONNECTED    │ (Service cut off)
                    │  (Red)           │
                    └──────┬───────────┘
                           │
                ┌──────────┴──────────┐
                │                     │
        (Optional Reconnect)   (Stay Disconnected)
                │                     │
                ▼                     │
        ┌───────────────────┐         │
        │  RECONNECTED      │         │
        │  (Green)          │         │
        │  Service Restored │         │
        └───────────────────┘         │
                                      │
                                      ▼
                                     END

CANCELLATION PATH (at any point):
                    │
                    ▼
            ┌────────────────┐
            │   CANCELLED    │
            │   (Light Gray) │
            │   Order Void   │
            └────────────────┘
```

## Component Interaction Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                         LAYERS                                  │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  PRESENTATION LAYER (UI)                                       │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ Web Dashboard              Mobile App                    │  │
│  │ ├─ index.blade.php         DisconnectorAssignments.js   │  │
│  │ └─ assignments.blade.php                                │  │
│  └──────────────────────────────────────────────────────────┘  │
│                           ▲                                     │
│                           │ HTTP/API                            │
│                           ▼                                     │
│  BUSINESS LOGIC LAYER (Controllers)                            │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ DisconnectionController  DisconnectorApiController       │  │
│  │ ├─ index()                ├─ getAssignments()            │  │
│  │ ├─ saveAndAssign()        ├─ updateAssignmentStatus()   │  │
│  │ ├─ assignments()          ├─ getStats()                 │  │
│  │ └─ assignOrders()         └─ getOrders()                │  │
│  └──────────────────────────────────────────────────────────┘  │
│                           ▲                                     │
│                           │ Query/Command                       │
│                           ▼                                     │
│  DATA ACCESS LAYER (Models)                                    │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ DisconnectionOrder Model                                 │  │
│  │ ├─ consumer() relation                                   │  │
│  │ ├─ disconnector() relation                               │  │
│  │ ├─ assignTo()                                            │  │
│  │ ├─ markAsDisconnected()                                  │  │
│  │ ├─ markAsReconnected()                                   │  │
│  │ └─ Scopes (pending, assigned, active, etc)               │  │
│  └──────────────────────────────────────────────────────────┘  │
│                           ▲                                     │
│                           │ SQL                                 │
│                           ▼                                     │
│  DATA LAYER (Database)                                         │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ MySQL/MariaDB                                            │  │
│  │ ├─ disconnection_orders                                  │  │
│  │ ├─ consumer_zone (related)                               │  │
│  │ └─ users (related)                                       │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

## API Request/Response Flow

```
┌─────────────────────────────────────────────────────────────┐
│ REQUEST: GET /api/disconnector/assignments?disconnector_id=2│
└────────────────────────────┬────────────────────────────────┘
                             │
                             ▼
                    ┌────────────────────────┐
                    │ API Route Handler      │
                    │ (routes/api.php)       │
                    └────────────┬───────────┘
                                 │
                                 ▼
                    ┌────────────────────────────────────┐
                    │ DisconnectorApiController          │
                    │ getAssignments($request)           │
                    │ ──────────────────────────────────│
                    │ 1. Validate input                  │
                    │ 2. Build query                     │
                    │ 3. Eager load relations            │
                    │ 4. Format response                 │
                    └────────────────┬───────────────────┘
                                     │
                                     ▼
                    ┌────────────────────────────────────┐
                    │ DisconnectionOrder Model           │
                    │ Query Database                     │
                    │ with('consumer', 'disconnector')   │
                    └────────────────┬───────────────────┘
                                     │
                                     ▼
                    ┌────────────────────────────────────┐
                    │ Database Query                     │
                    │ SELECT * FROM disconnection_orders │
                    │ WHERE disconnector_id = 2 ...      │
                    └────────────────┬───────────────────┘
                                     │
                                     ▼
                    ┌────────────────────────────────────┐
                    │ Database Results                   │
                    │ [Order1, Order2, Order3, ...]      │
                    └────────────────┬───────────────────┘
                                     │
                                     ▼
                    ┌────────────────────────────────────┐
                    │ Format Response                    │
                    │ Map to mobile format               │
                    │ Add type & assignment_type         │
                    └────────────────┬───────────────────┘
                                     │
                                     ▼
        ┌────────────────────────────────────────────────────┐
        │ RESPONSE (JSON)                                   │
        │ {                                                │
        │   "success": true,                              │
        │   "assignments": [                              │
        │     {                                           │
        │       "id": 1,                                  │
        │       "account_no": "HWD-001-001",              │
        │       "account_name": "John Doe",               │
        │       "address": "123 Main St",                 │
        │       "zone_code": "A1",                        │
        │       "total_outstanding": 5000.00,             │
        │       "disconnection_date": "2026-01-15",       │
        │       "status": "assigned",                     │
        │       "type": "disconnection",                  │
        │       "assignment_type": "disconnection"        │
        │     }                                           │
        │   ],                                            │
        │   "count": 1                                    │
        │ }                                               │
        └────────────────────────────────────────────────────┘
                             │
                             ▼
                    ┌────────────────────┐
                    │ Mobile App         │
                    │ Receives Response  │
                    │ Updates UI         │
                    │ Displays List      │
                    └────────────────────┘
```

---

These diagrams show:
1. **Complete System Architecture** - How all parts connect
2. **Data Flow** - How data moves through the system
3. **Database Relationships** - How tables relate
4. **Status Transitions** - How orders progress
5. **Component Interactions** - How layers communicate
6. **API Flow** - Request to response cycle
