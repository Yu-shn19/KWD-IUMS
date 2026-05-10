/**
 * Calendar date in the device local timezone as YYYY-MM-DD.
 * Avoids UTC day shift from Date.prototype.toISOString().slice(0, 10).
 */
export function getLocalDateYYYYMMDD(input = new Date()) {
  const d = input instanceof Date ? input : new Date(input);
  if (Number.isNaN(d.getTime())) {
    const now = new Date();
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  }
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

/**
 * Extract YYYY-MM-DD from a string or value without treating plain dates as UTC.
 */
function parseToYYYYMMDD(raw) {
  if (raw == null || raw === '') return null;
  const s = String(raw).trim();
  const m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (m) return `${m[1]}-${m[2]}-${m[3]}`;
  const d = new Date(s);
  if (Number.isNaN(d.getTime())) return null;
  return getLocalDateYYYYMMDD(d);
}

/**
 * Reading date for submit-reading / SQLite: use the meter reading schedule dates from the route
 * (same idea as bill_date on MeterReadingSchedule), not "today" unless the schedule has no date.
 */
export function getReadingDateFromMeterSchedule(scheduleLike) {
  if (!scheduleLike || typeof scheduleLike !== 'object') {
    return getLocalDateYYYYMMDD();
  }
  const raw =
    scheduleLike.bill_date ??
    scheduleLike.billDate ??
    scheduleLike.reading_date ??
    scheduleLike.readingDate ??
    scheduleLike.bill_month ??
    scheduleLike.billMonth ??
    null;
  const ymd = parseToYYYYMMDD(raw);
  return ymd || getLocalDateYYYYMMDD();
}
