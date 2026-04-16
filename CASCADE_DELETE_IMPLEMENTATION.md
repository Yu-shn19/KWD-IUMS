# Cascade Delete Implementation - Meter Reading Schedules to Consumer Ledgers

## Overview
When a meter reading schedule is deleted, all related consumer ledger entries are automatically deleted (cascade delete).

## Changes Made

### 1. **Model: `MeterReadingSchedule.php`**
Added a `boot()` method with a `deleting` event listener that cascades deletion to ledger entries:

```php
protected static function boot()
{
    parent::boot();

    static::deleting(function ($schedule) {
        // Delete all consumer ledger entries associated with this schedule
        $schedule->ledgerEntries()->delete();
    });
}
```

**Purpose**: 
- Provides model-level cascade delete safety
- Ensures child records are deleted even if database constraints fail
- Triggers any ledger model events (if needed)

### 2. **Migration: Update Existing Constraint**
File: `2025_12_09_180646_add_schedule_and_downloaded_reading_relationships_to_consumer_ledgers_table.php`

Changed foreign key from:
```php
->onDelete('set null')  // Old - would leave orphaned records
```

To:
```php
->onDelete('cascade')   // New - deletes ledger entries with schedule
```

### 3. **New Migration: Fix Existing Database**
File: `2026_01_01_000001_fix_cascade_delete_consumer_ledgers_schedule.php`

Alters the existing foreign key constraint in the database to use CASCADE instead of SET NULL.

**Run this migration to update existing database:**
```bash
php artisan migrate
```

## How It Works

### Deletion Flow
```
DELETE FROM meter_reading_schedules WHERE id = 5
    ↓
Database CASCADE foreign key triggers
    ↓
DELETE FROM consumer_ledgers WHERE schedule_id = 5  (Automatic)
    ↓
MeterReadingSchedule model deleting event fires
    ↓
Additional PHP-level deletion (redundant but safe)
    ↓
✅ All related records deleted cleanly
```

### Double Safety Layer
1. **Database Level** (Fast, enforced by MySQL)
   - Foreign key with `CASCADE` automatically deletes ledger entries
   
2. **Model Level** (Redundant safety)
   - Laravel model event ensures deletion even if DB constraint fails

## Example Usage

### Before (orphaned records would remain)
```php
$schedule = MeterReadingSchedule::find(5);
$schedule->delete();

// Result: Schedule deleted, but consumer_ledgers with schedule_id = 5 remain ❌
```

### After (all related records deleted)
```php
$schedule = MeterReadingSchedule::find(5);
$schedule->delete();

// Result: 
// - meter_reading_schedule (id=5) deleted ✅
// - All consumer_ledgers (schedule_id=5) deleted ✅
// - Database integrity maintained ✅
```

## Database Query Example

When you delete a schedule, the following happens automatically:

```sql
-- Your delete command
DELETE FROM meter_reading_schedules WHERE id = 5;

-- MySQL automatically executes (due to CASCADE constraint)
DELETE FROM consumer_ledgers WHERE schedule_id = 5;
```

## Relationships Affected

### MeterReadingSchedule Model
```php
public function ledgerEntries()
{
    return $this->hasMany(ConsumerLedger::class, 'schedule_id');
}
```

### ConsumerLedger Model
```php
public function schedule()
{
    return $this->belongsTo(MeterReadingSchedule::class, 'schedule_id');
}
```

**Before**: `onDelete('set null')` → ledger.schedule_id becomes NULL  
**After**: `onDelete('cascade')` → ledger record deleted entirely

## Testing Cascade Delete

### Test in Laravel Tinker
```php
php artisan tinker

# Create test schedule and ledger
$schedule = MeterReadingSchedule::latest()->first();
$ledgersCount = $schedule->ledgerEntries()->count();
echo "Ledgers before delete: $ledgersCount";

# Delete the schedule
$schedule->delete();

# Verify ledgers are gone
$orphanedLedgers = ConsumerLedger::where('schedule_id', $schedule->id)->count();
echo "Orphaned ledgers after delete: $orphanedLedgers"; // Should be 0
```

### Test in Laravel Test Case
```php
public function test_deleting_schedule_deletes_related_ledgers()
{
    $schedule = MeterReadingSchedule::factory()->create();
    $ledger = ConsumerLedger::factory()->create(['schedule_id' => $schedule->id]);
    
    $scheduleId = $schedule->id;
    $schedule->delete();
    
    // Verify schedule deleted
    $this->assertNull(MeterReadingSchedule::find($scheduleId));
    
    // Verify related ledger deleted
    $this->assertNull(ConsumerLedger::find($ledger->id));
}
```

## Migration Steps

1. **Review the changes**
   ```bash
   php artisan migrate:status
   ```

2. **Run the migration**
   ```bash
   php artisan migrate
   ```

3. **Verify the foreign key**
   ```bash
   # Check database constraints
   SHOW CREATE TABLE consumer_ledgers;
   # Look for: CONSTRAINT ... FOREIGN KEY ... REFERENCES meter_reading_schedules ... ON DELETE CASCADE
   ```

## Rollback (if needed)

If you need to revert to SET NULL behavior:

```bash
php artisan migrate:rollback --step=1
```

This will restore the previous `SET NULL` behavior.

## Important Notes

⚠️ **Data Loss Warning**
- Deleting a meter reading schedule will permanently delete all related consumer ledger entries
- This cannot be undone without database backup
- Consider archiving instead of deleting for historical records

✅ **Best Practices**
- Create backups before bulk deletions
- Use soft deletes if you need to preserve history: `$schedule->delete()` → mark as deleted, don't actually delete
- Log deletions for audit purposes
- Consider implementing soft deletes: `SoftDeletes` trait

## Alternative: Soft Deletes (Recommended)

For better data preservation, consider using Laravel's soft deletes:

```php
use Illuminate\Database\Eloquent\SoftDeletes;

class MeterReadingSchedule extends Model
{
    use SoftDeletes;
    
    protected $dates = ['deleted_at'];
}
```

This will mark records as deleted without actually removing them from the database.

## Summary

✅ Cascade delete implemented at two levels:
1. Database foreign key constraint (CASCADE)
2. Laravel model event listener (redundant safety)

✅ All related consumer ledger entries are automatically deleted when schedule is deleted

✅ Database integrity maintained

✅ Ready for production use

---

**Status**: ✅ Implementation Complete  
**Date**: January 1, 2026  
**Files Modified**: 3
- MeterReadingSchedule.php (added boot method)
- 2025_12_09_180646_add_schedule_and_downloaded_reading_relationships_to_consumer_ledgers_table.php (updated foreign key)
- 2026_01_01_000001_fix_cascade_delete_consumer_ledgers_schedule.php (new migration)
