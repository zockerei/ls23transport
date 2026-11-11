// Same limit as the server (MAX_MONTHS in forms/anfrage.php).
const MAX_MONTHS = 12;

/** "YYYY-MM-DD" from the local date parts. toISOString() would give the UTC date (yesterday after midnight). */
function isoDate(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

/** The date picker only offers days the server accepts: today up to MAX_MONTHS ahead. */
function setDateRange(form) {
  const today = new Date();
  const max = new Date(
    today.getFullYear(),
    today.getMonth() + MAX_MONTHS,
    today.getDate(),
  );

  for (const input of form.querySelectorAll('input[type="date"]')) {
    input.min = isoDate(today);
    input.max = isoDate(max);
  }
}

/** Time trap: the server treats a submit less than 3 s after page load as spam. */
function setTimestamp(form) {
  const ts = form.elements.namedItem("ts");
  if (ts) {
    ts.value = String(Date.now());
  }
}

export function initForm(form) {
  setTimestamp(form);
  setDateRange(form);
}
