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
export function parseToYYYYMMDD(raw) {
  if (raw == null || raw === '') return null;
  const s = String(raw).trim();
  const m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (m) return `${m[1]}-${m[2]}-${m[3]}`;
  const d = new Date(s);
  if (Number.isNaN(d.getTime())) return null;
  return getLocalDateYYYYMMDD(d);
}

/**
 * Whole calendar days from previous reading date to current reading date.
 * Uses local YYYY-MM-DD so timezone does not shift the count.
 * Returns null when either date is missing/invalid.
 */
export function countCalendarDaysBetween(startRaw, endRaw) {
  const startYmd = parseToYYYYMMDD(startRaw);
  const endYmd = parseToYYYYMMDD(endRaw);
  if (!startYmd || !endYmd) return null;
  const start = new Date(`${startYmd}T12:00:00`);
  const end = new Date(`${endYmd}T12:00:00`);
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return null;
  const dayMs = 24 * 60 * 60 * 1000;
  return Math.max(0, Math.round((end.getTime() - start.getTime()) / dayMs));
}

/**
 * Format a moment for receipt display under the meter reader name (Manila).
 * Accepts:
 * - Manila stored string: "2026-09-19 12:16:05" (no TZ conversion)
 * - Date / UTC ISO: converted to Asia/Manila
 * Example output: "Sep 19, 2026, 12:16 PM"
 */
export function formatManilaDateTime(input = new Date()) {
  if (input == null || input === '') return null;

  if (typeof input === 'string') {
    const s = input.trim();
    // Manila wall-clock storage (no Z / offset) — format as-is
    const manilaStored = s.match(
      /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?(?:\.\d+)?$/
    );
    if (manilaStored && !/[zZ]$/.test(s) && !/[+-]\d{2}:?\d{2}$/.test(s)) {
      const year = Number(manilaStored[1]);
      const month = Number(manilaStored[2]);
      const day = Number(manilaStored[3]);
      const hour = Number(manilaStored[4]);
      const minute = Number(manilaStored[5]);
      const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
      const ampm = hour >= 12 ? 'PM' : 'AM';
      const hour12 = hour % 12 || 12;
      return `${months[month - 1]} ${day}, ${year}, ${String(hour12).padStart(2, '0')}:${String(minute).padStart(2, '0')} ${ampm}`;
    }
  }

  const d = input instanceof Date ? input : new Date(input);
  if (Number.isNaN(d.getTime())) return null;
  try {
    return d.toLocaleString('en-PH', {
      timeZone: 'Asia/Manila',
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      hour12: true,
    });
  } catch (_) {
    return d.toLocaleString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      hour12: true,
    });
  }
}

/**
 * Current date/time as Manila wall clock for read_at storage.
 * Example: "2026-09-19 12:16:05" (Asia/Manila, not UTC).
 */
export function getManilaNowStored() {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Manila',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
  }).formatToParts(new Date());
  const get = (type) => parts.find((p) => p.type === type)?.value ?? '00';
  return `${get('year')}-${get('month')}-${get('day')} ${get('hour')}:${get('minute')}:${get('second')}`;
}

/** @deprecated Prefer getManilaNowStored for read_at — kept for any UTC callers. */
export function getNowIso() {
  return getManilaNowStored();
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
