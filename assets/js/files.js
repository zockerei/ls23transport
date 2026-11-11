/*
 * File input with a list: every chosen file is shown with a remove button, and choosing more files
 * adds to the list instead of replacing it (what a plain <input type="file" multiple> does).
 * The list lives in the input itself (input.files), so FormData(form) sends exactly what is shown.
 * Without JS the plain input still works.
 */

const sizeFormat = new Intl.NumberFormat("de-AT", { maximumFractionDigits: 1 });

/** "1,2 MB" or "340 KB". */
function formatSize(bytes) {
  const mb = bytes / (1024 * 1024);
  return mb >= 0.1
    ? `${sizeFormat.format(mb)} MB`
    : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/** The same file chosen twice is only added once. */
function fileKey(file) {
  return `${file.name}|${file.size}|${file.lastModified}`;
}

function createItem(file, index) {
  const item = document.createElement("li");
  item.className = "file-list__item";

  const name = document.createElement("span");
  name.className = "file-list__name";
  name.textContent = file.name;

  const size = document.createElement("span");
  size.className = "file-list__size";
  size.textContent = formatSize(file.size);

  const button = document.createElement("button");
  button.type = "button";
  button.className = "button button--link file-list__remove";
  button.dataset.index = String(index);
  button.textContent = "Entfernen";
  // Five "Entfernen" buttons sound the same in a screen reader. The name says which file.
  button.setAttribute("aria-label", `${file.name} entfernen`);

  item.append(name, size, button);
  return item;
}

export function initFileList(input) {
  let files = [];

  const list = document.createElement("ul");
  list.className = "file-list";
  list.hidden = true;

  // Screen readers don't notice a list changing. This line tells them.
  const status = document.createElement("p");
  status.className = "visually-hidden";
  status.setAttribute("aria-live", "polite");

  input.after(list, status);

  /** Writes the list into the input and redraws it. */
  function update() {
    // input.files can't be edited directly, but it can be replaced by the files of a DataTransfer.
    const transfer = new DataTransfer();
    for (const file of files) {
      transfer.items.add(file);
    }
    input.files = transfer.files;

    list.replaceChildren(...files.map(createItem));
    list.hidden = files.length === 0;
  }

  input.addEventListener("change", () => {
    // The browser has just replaced input.files with the new choice. Add it to the old list.
    const known = new Set(files.map(fileKey));
    const added = [...input.files].filter((file) => !known.has(fileKey(file)));
    files = [...files, ...added];
    update();

    if (added.length > 0) {
      status.textContent =
        added.length === 1
          ? `${added[0].name} hinzugefügt.`
          : `${added.length} Dateien hinzugefügt.`;
    }
  });

  list.addEventListener("click", (event) => {
    const button = event.target.closest(".file-list__remove");
    if (!button) {
      return;
    }

    const index = Number(button.dataset.index);
    const [removed] = files.splice(index, 1);
    update();
    status.textContent = `${removed.name} entfernt.`;

    // The clicked button is gone. Move focus to the next file, else the previous, else the input,
    // so keyboard users don't land at the top of the page.
    const buttons = list.querySelectorAll(".file-list__remove");
    (buttons[index] ?? buttons[index - 1] ?? input).focus();
  });
}
