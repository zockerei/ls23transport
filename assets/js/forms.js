// Same limit as the server (MAX_MONTHS in forms/inc/validate.php).
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

function preselectService(form) {
  const slug = new URLSearchParams(location.search).get("leistung");
  if (!slug) {
    return;
  }

  for (const input of form.querySelectorAll('input[name="service[]"]')) {
    if (input.value === slug) {
      input.checked = true;
    }
  }
}

const SVG_NS = "http://www.w3.org/2000/svg";
const FALLBACK_MESSAGE =
  "Das Formular konnte gerade nicht gesendet werden. Bitte versuchen Sie es später noch einmal oder rufen Sie uns an.";

function controlsFor(form, key) {
  const item =
    form.elements.namedItem(key) ?? form.elements.namedItem(`${key}[]`);
  if (!item) {
    return [];
  }
  return item instanceof RadioNodeList ? [...item] : [item];
}

function isGroup(control) {
  return control.type === "checkbox" || control.type === "radio";
}

function addToken(element, attribute, token) {
  const tokens = (element.getAttribute(attribute) ?? "").split(" ");
  element.setAttribute(attribute, [...tokens, token].join(" ").trim());
}

function removeToken(element, attribute, token) {
  const tokens = (element.getAttribute(attribute) ?? "")
    .split(" ")
    .filter((t) => t && t !== token);
  if (tokens.length > 0) {
    element.setAttribute(attribute, tokens.join(" "));
  } else {
    element.removeAttribute(attribute);
  }
}

function createMessage(id, text) {
  const message = document.createElement("p");
  message.className = "field__error";
  message.id = id;

  const icon = document.createElementNS(SVG_NS, "svg");
  icon.setAttribute("class", "icon");
  icon.setAttribute("aria-hidden", "true");
  icon.setAttribute("width", "24");
  icon.setAttribute("height", "24");
  const use = document.createElementNS(SVG_NS, "use");
  use.setAttribute("href", "/assets/icons/sprite.svg#circle-alert");
  icon.append(use);

  const prefix = document.createElement("span");
  prefix.className = "visually-hidden";
  prefix.textContent = "Fehler: ";

  const body = document.createElement("span");
  body.append(prefix, text);

  message.append(icon, body);
  return message;
}

function showFieldError(form, key, text) {
  const controls = controlsFor(form, key);
  if (controls.length === 0) {
    return null;
  }

  const first = controls[0];
  const id = `${first.id}-error`;
  const message = createMessage(id, text);

  if (isGroup(first)) {
    // Group: the message goes in the fieldset, after the hint, before the choices.
    const fieldset = first.closest("fieldset");
    fieldset.querySelector(".choices").before(message);
    addToken(fieldset, "aria-describedby", id);
  } else {
    const row = first.closest(".form__row");
    (row ?? first).before(message);
    addToken(first, "aria-describedby", id);
  }

  for (const control of controls) {
    control.setAttribute("aria-invalid", "true");
  }
  return first;
}

/** Removes everything from the last attempt: messages, aria-invalid, summary. */
function clearErrors(form) {
  for (const message of form.querySelectorAll(".field__error")) {
    for (const described of form.querySelectorAll(
      `[aria-describedby~="${message.id}"]`,
    )) {
      removeToken(described, "aria-describedby", message.id);
    }
    message.remove();
  }
  for (const control of form.querySelectorAll('[aria-invalid="true"]')) {
    control.removeAttribute("aria-invalid");
  }
  form.querySelector(".error-summary")?.remove();
}

function createSummary(form, title) {
  const summary = document.createElement("div");
  summary.className = "error-summary";
  summary.setAttribute("role", "alert");
  summary.tabIndex = -1;

  const heading = document.createElement("h3");
  heading.className = "error-summary__title";
  heading.textContent = title;
  summary.append(heading);

  form.prepend(summary);
  return summary;
}

function showErrors(form, errors) {
  const summary = createSummary(form, "Bitte prüfen Sie Ihre Angaben");
  const list = document.createElement("ul");
  list.className = "error-summary__list";

  for (const [key, text] of Object.entries(errors)) {
    const control = showFieldError(form, key, text);
    const item = document.createElement("li");
    if (control) {
      const link = document.createElement("a");
      link.href = `#${control.id}`;
      link.textContent = text;
      item.append(link);
    } else {
      item.textContent = text;
    }
    list.append(item);
  }

  summary.append(list);
  summary.focus();
}

function showFailure(form, text) {
  const summary = createSummary(form, "Nicht gesendet");
  const paragraph = document.createElement("p");
  paragraph.textContent = text;
  summary.append(paragraph);

  // Phone number from the header, so it isn't written into the JS a second time.
  const phone = document.querySelector('a[href^="tel:"]');
  if (phone) {
    const link = document.createElement("a");
    link.href = phone.getAttribute("href");
    link.textContent = phone.textContent.trim();
    const line = document.createElement("p");
    line.append(link);
    summary.append(line);
  }
  summary.focus();
}

function handleSummaryClick(event) {
  const link = event.target.closest(".error-summary a[href^='#']");
  if (!link) {
    return;
  }
  const control = document.getElementById(link.hash.slice(1));
  if (!control) {
    return;
  }

  event.preventDefault();
  const caption = isGroup(control)
    ? control.closest("fieldset").querySelector("legend")
    : control.labels[0];
  (caption ?? control).scrollIntoView();
  control.focus({ preventScroll: true });
}

function initSubmit(form) {
  const button = form.querySelector('[type="submit"]');
  const buttonText = button.textContent;
  let sending = false;

  function setSending(value) {
    sending = value;
    button.textContent = value ? "Wird gesendet …" : buttonText;
  }

  form.noValidate = true;
  form.addEventListener("click", handleSummaryClick);

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    // A double click must not send the request twice. The button stays enabled (§4).
    if (sending) {
      return;
    }
    setSending(true);

    let response = null;
    let data = null;
    try {
      response = await fetch(form.action, {
        method: "POST",
        body: new FormData(form),
        headers: { Accept: "application/json" },
      });
      data = await response.json();
    } catch {
      // Network error, or no JSON (e.g. on the Cloudflare preview, which doesn't run PHP).
    }

    clearErrors(form);
    if (data?.ok) {
      // Stays "sending" until the next page is there, so nobody can send it again.
      location.assign(data.redirect);
      return;
    }

    if (response?.status === 422 && data?.errors) {
      showErrors(form, data.errors);
    } else {
      showFailure(form, data?.message ?? FALLBACK_MESSAGE);
    }
    setSending(false);
  });

  // Back from /danke/: the browser may restore this page as it was (bfcache), still "sending".
  window.addEventListener("pageshow", (event) => {
    if (event.persisted) {
      setSending(false);
    }
  });
}

export function initForm(form) {
  setTimestamp(form);
  setDateRange(form);
  preselectService(form);
  initSubmit(form);
}
